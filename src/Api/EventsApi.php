<?php
declare(strict_types=1);

namespace App\Api;

use App\Events;
use App\RateLimiter;
use App\Security;
use App\Slots;
use App\Themes;

/**
 * The JSON endpoints. Each method returns [data, HTTP status]; problems are
 * thrown as ApiError and turned into JSON by App.
 */
final class EventsApi
{
    public const MAX_NAME = 40;
    private const SIGNIN_FAILURES = 10;      // per address and event...
    private const SIGNIN_WINDOW = 600;       // ...in ten minutes

    public function __construct(
        private Events $events,
        private Security $security,
        private RateLimiter $limiter,
        private Themes $themes,
        private array $config,
    ) {
    }

    /** POST /api/events */
    public function create(array $in): array
    {
        // Bots fill in every field they see; people never see this one.
        if (($in['website'] ?? '') !== '') {
            throw new ApiError('rejected', 400);
        }

        $bucket = 'create|' . $this->security->clientKey();
        if ($this->limiter->count($bucket, 3600) >= (int) $this->config['limits']['events_per_hour']) {
            throw new ApiError('rate_limited', 429);
        }

        $expected = $this->config['create_passphrase'];
        if ($expected !== null && $expected !== '') {
            $given = is_string($in['passphrase'] ?? null) ? $in['passphrase'] : '';
            $hashed = password_get_info($expected)['algo'] !== null;
            $ok = $hashed ? password_verify($given, $expected) : hash_equals($expected, $given);
            if (!$ok) {
                $this->limiter->hit($bucket); // wrong guesses use up the allowance too
                throw new ApiError('passphrase', 403);
            }
        }

        $data = EventInput::validate($in, $this->config, $this->themes);
        $this->events->purgeExpired();
        $created = $this->events->create($data, (int) $this->config['retention_days']);
        $this->limiter->hit($bucket);

        return [$created, 201];
    }

    /** GET /api/events/{slug} */
    public function show(string $slug, ?string $since = null): array
    {
        $event = $this->event($slug);
        return [$this->snapshot($event, $since), 200];
    }

    /** POST /api/events/{slug}/signin */
    public function signin(string $slug, array $in): array
    {
        $event = $this->event($slug);
        $name = EventInput::cleanText($in['name'] ?? null);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            throw new ApiError('name', 422, ['max' => self::MAX_NAME]);
        }
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';
        if (strlen($password) > 200) {
            throw new ApiError('password');
        }

        $bucket = 'signin|' . $slug . '|' . $this->security->clientKey();
        if ($this->limiter->count($bucket, self::SIGNIN_WINDOW) >= self::SIGNIN_FAILURES) {
            throw new ApiError('rate_limited', 429);
        }

        $existing = $this->events->findResponseByName($event['id'], $name);
        if ($existing) {
            // A name saved without a password can be edited by anyone who types it.
            if ($existing['password_hash'] !== null && !password_verify($password, $existing['password_hash'])) {
                $this->limiter->hit($bucket);
                throw new ApiError('signin', 403);
            }
            $id = (int) $existing['id'];
        } else {
            if ($this->events->countResponses($event['id']) >= (int) $this->config['limits']['max_respondents']) {
                throw new ApiError('full', 409, ['max' => $this->config['limits']['max_respondents']]);
            }
            $hash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
            $id = $this->events->addResponse($event['id'], $name, $hash);
        }

        $this->security->startSession($slug, $id);
        return [$this->snapshot($event, null, $id), 200];
    }

    /** POST /api/events/{slug}/signout */
    public function signout(string $slug): array
    {
        $this->security->endSession($slug);
        return [['ok' => true], 200];
    }

    /** PUT /api/events/{slug}/responses/me : the full slot list replaces the old one. */
    public function saveMine(string $slug, array $in): array
    {
        $event = $this->event($slug);
        $id = $this->security->session($slug);
        $response = $id === null ? null : $this->events->responseById($event['id'], $id);
        if (!$response) {
            throw new ApiError('signed_out', 401);
        }
        if (!is_array($in['slots'] ?? null)) {
            throw new ApiError('slots');
        }
        $slots = Slots::sanitize($event, $in['slots']);
        $updated = $this->events->saveSlots((int) $response['id'], $slots);
        return [['ok' => true, 'saved' => count($slots), 'updated_at' => $updated], 200];
    }

    // ----- The private admin link. Every call carries the token from that link. -----

    /**
     * POST /api/events/{slug}/admin/update
     * Any of: title, theme, add_days (dates or weekday numbers to add; days are never removed).
     */
    public function adminUpdate(string $slug, array $in): array
    {
        $event = $this->adminEvent($slug, $in);

        $title = array_key_exists('title', $in) ? EventInput::title($in['title']) : $event['title'];
        $theme = array_key_exists('theme', $in) ? EventInput::theme($in['theme'], $this->themes) : $event['theme'];

        $days = $event['days'];
        $expires = null;
        if (array_key_exists('add_days', $in) && $in['add_days'] !== []) {
            if (!is_array($in['add_days'])) {
                throw new ApiError('days');
            }
            $days = EventInput::days(
                $event['mode'],
                array_merge($event['days'], array_values($in['add_days'])),
                (int) $this->config['limits']['max_days']
            );
            if ($event['mode'] === 'dates') {
                $expires = Events::expiry('dates', $days, (int) $this->config['retention_days']);
            }
        }

        $this->events->update($event['id'], $title, $days, $theme, $expires);
        return [$this->adminSnapshot($this->event($slug)), 200];
    }

    /** POST /api/events/{slug}/admin/remove : delete one respondent and their times. */
    public function adminRemove(string $slug, array $in): array
    {
        $event = $this->adminEvent($slug, $in);
        $id = $in['response_id'] ?? null;
        if (!is_int($id) || !$this->events->removeResponse($event['id'], $id)) {
            throw new ApiError('respondent', 404);
        }
        return [$this->adminSnapshot($event), 200];
    }

    /** POST /api/events/{slug}/admin/delete : the event and all its responses, for good. */
    public function adminDelete(string $slug, array $in): array
    {
        $event = $this->adminEvent($slug, $in);
        $this->events->delete($event['id']);
        return [['ok' => true], 200];
    }

    /** What the admin page shows: the event, and each respondent without their times. */
    public function adminSnapshot(array $event): array
    {
        return [
            'event' => $this->events->publicEvent($event) + [
                'theme' => $event['theme'],
                'expires_at' => $event['expires_at'],
            ],
            'responses' => array_map(
                static fn (array $r) => [
                    'id' => $r['id'],
                    'name' => $r['name'],
                    'count' => count($r['slots']),
                    'locked' => $r['locked'],
                ],
                $this->events->responses($event['id'])
            ),
        ];
    }

    private function adminEvent(string $slug, array $in): array
    {
        $event = $this->event($slug);
        $token = $in['token'] ?? null;
        if (!is_string($token) || !$this->events->isAdmin($event, $token)) {
            throw new ApiError('admin', 403);
        }
        return $event;
    }

    /** The event, everyone's responses (no hashes), and who the visitor is signed in as. */
    public function snapshot(array $event, ?string $since = null, ?int $sessionId = null): array
    {
        $responses = $this->events->responses($event['id']);
        $version = $this->events->version($event, $responses);
        $sessionId ??= $this->security->session($event['slug']);
        $me = null;
        foreach ($responses as $r) {
            if ($r['id'] === $sessionId) {
                $me = $r['name'];
            }
        }
        if ($since !== null && $since === $version) {
            return ['version' => $version, 'unchanged' => true, 'me' => $me];
        }
        return [
            'version' => $version,
            'event' => $this->events->publicEvent($event),
            'responses' => array_map(
                static fn (array $r) => ['name' => $r['name'], 'slots' => $r['slots']],
                $responses
            ),
            'me' => $me,
        ];
    }

    private function event(string $slug): array
    {
        return $this->events->find($slug) ?? throw new ApiError('not_found', 404);
    }
}
