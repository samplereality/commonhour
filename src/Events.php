<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Reads and writes events and responses. Every query is a prepared statement. */
final class Events
{
    private const SLUG_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array $data validated fields (see Api\EventInput)
     * @return array{slug: string, admin_token: string}
     */
    public function create(array $data, int $retentionDays): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $adminToken = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare(
            'INSERT INTO events (slug, title, mode, days, start_minute, end_minute, slot_minutes, timezone, theme,
                                 admin_token_hash, created_at, expires_at)
             VALUES (:slug, :title, :mode, :days, :start, :end, :slot, :tz, :theme, :hash, :created, :expires)'
        );
        for ($attempt = 0; ; $attempt++) {
            $slug = self::randomSlug();
            try {
                $stmt->execute([
                    'slug' => $slug,
                    'title' => $data['title'],
                    'mode' => $data['mode'],
                    'days' => json_encode($data['days']),
                    'start' => $data['start_minute'],
                    'end' => $data['end_minute'],
                    'slot' => $data['slot_minutes'],
                    'tz' => $data['timezone'],
                    'theme' => $data['theme'],
                    'hash' => hash('sha256', $adminToken),
                    'created' => $now->format('Y-m-d H:i:s'),
                    'expires' => self::expiry($data['mode'], $data['days'], $retentionDays),
                ]);
                return ['slug' => $slug, 'admin_token' => $adminToken];
            } catch (\PDOException $e) {
                if ($attempt >= 5) {
                    throw $e; // not a slug collision
                }
            }
        }
    }

    /**
     * When an event is deleted: retention is counted from its last date. A
     * weekly event has no last date, so it is counted from today.
     */
    public static function expiry(string $mode, array $days, int $retentionDays): string
    {
        $utc = new \DateTimeZone('UTC');
        $last = $mode === 'dates'
            ? new \DateTimeImmutable(max($days) . ' 23:59:59', $utc)
            : new \DateTimeImmutable('now', $utc);
        return $last->modify("+$retentionDays days")->format('Y-m-d H:i:s');
    }

    /** Admin edits. $expiresAt null leaves the deletion date as it is. */
    public function update(int $id, string $title, array $days, ?string $theme, ?string $expiresAt): void
    {
        $this->db->prepare(
            'UPDATE events SET title = ?, days = ?, theme = ?, expires_at = COALESCE(?, expires_at) WHERE id = ?'
        )->execute([$title, json_encode(array_values($days)), $theme, $expiresAt, $id]);
    }

    /** Deletes the event and, through the foreign key, every response to it. */
    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    }

    public function removeResponse(int $eventId, int $responseId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM responses WHERE event_id = ? AND id = ?');
        $stmt->execute([$eventId, $responseId]);
        return $stmt->rowCount() > 0;
    }

    /** An event that exists and has not expired, with its JSON columns decoded. */
    public function find(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9]{8,32}$/', $slug)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM events WHERE slug = ? AND expires_at > ?');
        $stmt->execute([$slug, gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        foreach (['id', 'start_minute', 'end_minute', 'slot_minutes'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['days'] = json_decode($row['days'], true) ?: [];
        return $row;
    }

    public function isAdmin(array $event, string $token): bool
    {
        return hash_equals($event['admin_token_hash'], hash('sha256', $token));
    }

    /** What the browser is allowed to know about an event. */
    public function publicEvent(array $event): array
    {
        return [
            'slug' => $event['slug'],
            'title' => $event['title'],
            'mode' => $event['mode'],
            'days' => $event['days'],
            'start_minute' => $event['start_minute'],
            'end_minute' => $event['end_minute'],
            'slot_minutes' => $event['slot_minutes'],
            'timezone' => $event['timezone'],
            'slots' => Slots::forEvent($event),
        ];
    }

    /** @return array<int, array{id: int, name: string, slots: array, updated_at: string, locked: bool}> oldest first */
    public function responses(int $eventId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slots, updated_at, password_hash IS NOT NULL AS locked FROM responses WHERE event_id = ? ORDER BY id'
        );
        $stmt->execute([$eventId]);
        return array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slots' => json_decode($r['slots'], true) ?: [],
            'updated_at' => $r['updated_at'],
            'locked' => (bool) $r['locked'],
        ], $stmt->fetchAll());
    }

    /** Changes whenever anything a visitor can see changes. */
    public function version(array $event, array $responses): string
    {
        $parts = [$event['title'], json_encode($event['days'])];
        foreach ($responses as $r) {
            $parts[] = $r['id'] . '@' . $r['updated_at'];
        }
        return substr(hash('sha256', implode('|', $parts)), 0, 16);
    }

    /** Names match regardless of case or surrounding space. */
    public function findResponseByName(int $eventId, string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM responses WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $wanted = self::nameKey($name);
        foreach ($stmt as $row) {
            if (self::nameKey($row['name']) === $wanted) {
                return $row;
            }
        }
        return null;
    }

    public function responseById(int $eventId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM responses WHERE event_id = ? AND id = ?');
        $stmt->execute([$eventId, $id]);
        return $stmt->fetch() ?: null;
    }

    public function countResponses(int $eventId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM responses WHERE event_id = ?');
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    public function addResponse(int $eventId, string $name, ?string $passwordHash): int
    {
        $this->db->prepare(
            'INSERT INTO responses (event_id, name, password_hash, slots, updated_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$eventId, $name, $passwordHash, '[]', self::now()]);
        return (int) $this->db->lastInsertId();
    }

    public function saveSlots(int $responseId, array $slots): string
    {
        $now = self::now();
        $this->db->prepare('UPDATE responses SET slots = ?, updated_at = ? WHERE id = ?')
            ->execute([json_encode(array_values($slots)), $now, $responseId]);
        return $now;
    }

    /** Delete events past their retention date (their responses go with them). */
    public function purgeExpired(): int
    {
        $stmt = $this->db->prepare('DELETE FROM events WHERE expires_at <= ?');
        $stmt->execute([gmdate('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** Microsecond precision, so two quick saves never look identical. */
    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    private static function randomSlug(int $length = 8): string
    {
        $slug = '';
        $max = strlen(self::SLUG_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $slug .= self::SLUG_ALPHABET[random_int(0, $max)];
        }
        return $slug;
    }
}
