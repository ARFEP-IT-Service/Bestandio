<?php

// Copy to config/config.php and adjust, or let /setup write it.
// The environment variable BESTANDIO_CONFIG may point to another path.

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'bestandio',
        'user'    => 'bestandio',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // 'auto' = Secure cookie flag only when the request arrives via HTTPS.
        // true   = always (use behind a TLS-terminating proxy).
        'secure_cookie' => 'auto',
        'per_page'      => 25,
    ],
];
