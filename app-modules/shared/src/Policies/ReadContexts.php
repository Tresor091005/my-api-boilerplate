<?php

declare(strict_types=1);

namespace Lahatre\Shared\Policies;

use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\ViewResolver;

/**
 * Reads config/read-contexts.php: which view a purpose gets on a route, and which permission it replaces.
 */
final class ReadContexts implements ViewResolver
{
    public function viewFor(string $purpose, Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        return $routeName === null ? null : $this->line($purpose, $routeName)['view'] ?? null;
    }

    /**
     * The purpose to check in place of $permission, when the accepted purpose's line replaces it.
     */
    public function replacementFor(string $permission): ?string
    {
        $purpose = ApiResponse::purpose();
        $routeName = request()->route()?->getName();

        if ($purpose === null || $routeName === null) {
            return null;
        }

        return ($this->line($purpose, $routeName)['replaces'] ?? null) === $permission ? $purpose : null;
    }

    /**
     * An alias is followed once. Contexts contain dots, so the table is read as a whole, not by config key.
     *
     * @return array{view: string, replaces: string}|null
     */
    private function line(string $purpose, string $routeName): ?array
    {
        $table = config('read-contexts');
        $context = $table[$purpose] ?? null;

        if (is_string($context)) {
            $context = $table[$context] ?? null;
        }

        return is_array($context) ? ($context[$routeName] ?? null) : null;
    }
}
