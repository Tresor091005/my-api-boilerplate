<?php

declare(strict_types=1);

$frontend = parse_url(env('FRONTEND_URL', 'http://localhost:28421'));
$origin = isset($frontend['scheme'], $frontend['host'])
    ? $frontend['scheme'].'://'.$frontend['host'].(isset($frontend['port']) ? ':'.$frontend['port'] : '')
    : null;

return [
    'paths'                    => ['v1/*', 'api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => $origin === null ? [] : [$origin],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 600,
    'supports_credentials'     => false,
];
