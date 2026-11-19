<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    /** Open the SQLite file, creating it (and its tables) on first run. */
    public static function open(string $path, string $schemaFile): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 3000');
        $pdo->exec((string) file_get_contents($schemaFile));
        return $pdo;
    }
}
