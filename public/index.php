<?php
declare(strict_types=1);

// Front controller: every page and /api request comes through here.

$root = dirname(__DIR__);

// Under PHP's built-in server (local development), let it serve real files.
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requested !== __FILE__ && is_file($requested)) {
        return false;
    }
}

require $root . '/src/bootstrap.php';

App\App::run($root);
