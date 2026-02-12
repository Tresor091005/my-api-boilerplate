<?php

declare(strict_types=1);

use Lahatre\Iam\Services\AuthContext;

if (!function_exists('authContext')) {
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
}
