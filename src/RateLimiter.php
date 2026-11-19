<?php
declare(strict_types=1);

namespace App;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    /** How many hits the bucket has had in the last $window seconds. */
    public function count(string $bucket, int $window): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND at > ?');
        $stmt->execute([$bucket, time() - $window]);
        return (int) $stmt->fetchColumn();
    }

    public function hit(string $bucket): void
    {
        $this->db->prepare('INSERT INTO rate_limits (bucket, at) VALUES (?, ?)')->execute([$bucket, time()]);
        // Old rows are of no use to anyone; clear them now and then.
        if (random_int(1, 20) === 1) {
            $this->db->prepare('DELETE FROM rate_limits WHERE at < ?')->execute([time() - 86400]);
        }
    }
}
