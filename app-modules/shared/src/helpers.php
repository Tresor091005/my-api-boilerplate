<?php

declare(strict_types=1);

use Lahatre\Iam\Auth\AuthContext;

if (!function_exists('authContext')) {
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
}

if (!function_exists('getDefaultTeamId')) {
    function getDefaultTeamId(): string
    {
        // TODO: SHOULD BE REMOVED when authContext is complete
        return '019c5b9b-697d-72e5-ab19-b2186fc49375';
    }
}
