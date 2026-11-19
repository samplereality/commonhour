<?php
declare(strict_types=1);

// Deletes events that are past their retention date, with their responses.
//
//   php bin/purge.php
//
// Optional. Expired events already stop being shown the moment they expire,
// and they are deleted whenever someone creates a new event. Run this from a
// daily cron job if you want them deleted on schedule even when the site is
// quiet (see "Deploying" in the README).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';

$config = App\Config::load($root);
$events = new App\Events(App\Db::open($config['data_path'], $root . '/schema.sql'));
printf("Deleted %d expired event(s).\n", $events->purgeExpired());
