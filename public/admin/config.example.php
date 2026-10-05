<?php
// Copy to config.php on the server (never commit config.php).
// Generate the password hash on the admin login page: when no valid hash is
// set, /admin/ shows a form that prints the line to paste here.
return [
    'password_hash' => '',                      // e.g. '$2y$12$....'
    'data_dir'      => __DIR__ . '/../data',
    'uploads_dir'   => __DIR__ . '/../uploads',
    'uploads_url'   => '/uploads',
    'max_upload'    => 10 * 1024 * 1024,        // bytes accepted from the browser
    'max_width'     => 1200,                    // banner image is scaled down to this width
    'session_ttl'   => 8 * 3600,
    'cookie_secure' => true,                    // false only for local testing over plain http
    'news_max'      => 50,
];
