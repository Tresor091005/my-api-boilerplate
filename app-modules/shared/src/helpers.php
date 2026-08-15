<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Shared\Http\Responses\ResponseContext;
use Lahatre\Shared\Support\DeterministicCursorPagination;

if (!function_exists('authContext')) {
    /** Resolve the current authenticated application context. */
    function authContext(): AuthContext
    {
        return app(AuthContext::class);
    }
}

if (!function_exists('currentOrganizationId')) {
    /** Resolve the organization identifier established for the current request. */
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
    /** Paginate a query with the project's deterministic cursor conventions. */
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

if (!function_exists('applyResponseContextToQuery')) {
    /** Apply response-context relation loads to an Eloquent query. */
    function applyResponseContextToQuery(Builder $query): Builder
    {
        return app(ResponseContext::class)->applyToQuery($query);
    }
}

if (!function_exists('responseRelationsToLoad')) {
    /**
     * Resolve response relations declared by the active response shape.
     */
    function responseRelationsToLoad(): array
    {
        return app(ResponseContext::class)->relationsToLoad();
    }
}
