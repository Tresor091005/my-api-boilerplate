<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Http\Request;

/**
 * Tells which view of an action's Resource answers a purpose the client gave in `for`. Binding one
 * turns the `for` parameter on. It only fixes the shape of the response: it never checks a permission.
 */
interface ViewResolver
{
    /**
     * The view of the action's Resource that answers this purpose here, or null when it is not accepted.
     */
    public function viewFor(string $purpose, Request $request): ?string;
}
