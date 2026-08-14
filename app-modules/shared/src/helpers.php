<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Shared\Support\DeterministicCursorPagination;

if (!function_exists('authContext')) {
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
}

if (!function_exists('currentOrganizationId')) {
    function currentOrganizationId(): string
    {
        $organizationId = getPermissionsTeamId();

        if (!is_string($organizationId) || $organizationId === '') {
            throw new AuthorizationException(__('shared::exceptions.organization_context_required'));
        }

        return $organizationId;
    }
}

if (!function_exists('stableCursorPaginate')) {
    function stableCursorPaginate(
        Builder|Illuminate\Database\Query\Builder|Relation $query,
        object $filters,
        array $columns = ['*'],
        string $tieBreakerColumn = 'id',
        string $cursorName = 'cursor'
    ): CursorPaginator {
        return DeterministicCursorPagination::paginate(
            $query,
            $filters,
            $columns,
            $tieBreakerColumn,
            $cursorName
        );
    }
}
