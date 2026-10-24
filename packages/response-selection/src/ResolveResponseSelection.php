<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Lahatre\ResponseSelection\Selection\SelectionTree;
use Lahatre\ResponseSelection\Selection\SelectionValidator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that reads and checks the `response`, `fields` and `include` query parameters before the
 * controller runs. Routes that do not answer with ApiResponse pass through untouched.
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
                        $parameter => [__('response-selection::validation.selection_without_resource', ['parameter' => $parameter])],
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
                'response' => [__('response-selection::validation.invalid_mode', ['allowed' => implode(', ', ResponseMode::values())])],
            ]);
        }

        if (!in_array($mode, $allowed, true)) {
            throw ValidationException::withMessages([
                'response' => [__('response-selection::validation.mode_not_allowed', ['mode' => $mode->value])],
            ]);
        }

        return $mode;
    }
}
