<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Illuminate\Http\Middleware\TrustProxies reads this value. Leave
| TRUSTED_PROXIES empty to trust no proxy: forwarded headers are then ignored
| and the request IP is the TCP peer. Set comma-separated IPs or CIDR ranges
| of the proxies that reach the API, or "*" to trust every caller, which is
| only safe when the API cannot be reached without the proxy. The trusted
| forwarded headers are set in bootstrap/app.php.
|
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
