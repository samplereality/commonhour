<?php
// Copy this file to config.php and edit the copy. config.php is git-ignored.
// Any key you leave out of config.php falls back to the value here.

return [
    // Shown in the wordmark, page titles and anywhere copy/en.php uses {site}.
    'site_name' => 'Common Hour',

    // Folder name inside themes/. Used unless an event picks its own theme.
    'default_theme' => 'default',

    // Which file in copy/ holds the user-facing strings.
    'locale' => 'en',

    // Only people who know this passphrase can create events. Set it in
    // config.php. Plain text, or a hash made with PHP's password_hash().
    // null = anyone who can reach the site can create events.
    'create_passphrase' => null,

    // Slot length preselected on the create form: 15 or 30.
    'default_slot_minutes' => 30,

    // Where the SQLite file lives. Keep it outside the web root.
    'data_path' => __DIR__ . '/data/app.sqlite',

    // Events are deleted this many days after their last date.
    'retention_days' => 90,

    'limits' => [
        'max_days' => 60,          // longest date range one event may cover
        'max_respondents' => 200,  // per event
        'events_per_hour' => 10,   // event creation, per IP address
    ],

    // URL prefix when the app is not at the root of its (sub)domain, e.g. '/meet'.
    // null = detect automatically, which is right whenever the (sub)domain's
    // document root is the public/ folder. Set it by hand only for the
    // fallback layout described in the README.
    'base_path' => null,

    // Content-Security-Policy header. Loosen it here if a theme loads fonts
    // from another host (e.g. add "font-src 'self' https://fonts.example").
    'csp' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
        . "font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
];
