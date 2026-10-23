<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Lahatre\Shared\Enums\ResponseMode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every `api` route. An action whose ApiResponse call the ResponseDeclarationRegistry knows
 * gets its response mode resolved from the HTTP method and `?response=`, `fields`/`include`
 * (query string only) parsed and checked against its Resource definitions, and the ResponseContext
 * filled, before the FormRequest and the controller run. A legacy action passes through untouched.
 */
class ResolveResponseSelection
{
    public function __construct(
        protected ResponseContext $context,
        protected ResponseDeclarationRegistry $declarations,
        protected SelectionParser $parser,
        protected SelectionValidator $validator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $declaration = $route instanceof Route ? $this->declarations->for($route) : null;

        if ($declaration === null) {
            return $next($request);
        }

        $mode = $this->resolveMode($request, $declaration->isNoContentOnly());

        if ($mode === ResponseMode::None) {
            foreach (['fields', 'include'] as $parameter) {
                if ($request->query->has($parameter)) {
                    throw ValidationException::withMessages([
                        $parameter => [__('shared::response.selection_without_resource', ['parameter' => $parameter])],
                    ]);
                }
            }

            $tree = SelectionTree::rootFields();
        } else {
            $tree = $this->parser->parse($request->query('fields'), $request->query('include'));

            if ($declaration->resourceClass !== null) {
                $this->validator->validate($tree->root(), $declaration->resourceClass);
            }
        }

        $this->context->start($mode, $tree, $declaration);

        try {
            return $next($request);
        } finally {
            $this->context->reset();
        }
    }

    protected function resolveMode(Request $request, bool $noContentOnly): ResponseMode
    {
        $allowed = ResponseMode::allowedFor(strtoupper($request->getMethod()), $noContentOnly);
        $requested = $request->query('response');

        if ($requested === null) {
            return $allowed[0];
        }

        $mode = is_string($requested) ? ResponseMode::tryFrom($requested) : null;

        if ($mode === null) {
            throw ValidationException::withMessages([
                'response' => [__('shared::response.invalid_mode', ['allowed' => implode(', ', ResponseMode::values())])],
            ]);
        }

        if (!in_array($mode, $allowed, true)) {
            throw ValidationException::withMessages([
                'response' => [__('shared::response.mode_not_allowed', ['mode' => $mode->value])],
            ]);
        }

        return $mode;
    }
}
