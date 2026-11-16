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
use Lahatre\ResponseSelection\Selection\SelectionViews;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that reads and checks the `response`, `fields`, `include` and `for` query parameters before the
 * controller runs. Routes that do not answer with ApiResponse pass through untouched.
 */
class ResolveResponseSelection
{
    public function __construct(
        protected ResponseContext $context,
        protected ResponseDeclarationRegistry $declarations,
        protected SelectionParser $parser,
        protected SelectionValidator $validator,
        protected SelectionViews $views = new SelectionViews,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $declaration = $route instanceof Route ? $this->declarations->for($route) : null;

        if ($declaration === null) {
            return $next($request);
        }

        $mode = $this->resolveMode($request, $declaration->isNoContentOnly());

        $resolver = $this->viewResolver();
        $purpose = null;

        if ($mode === ResponseMode::None) {
            foreach ($resolver === null ? ['fields', 'include'] : ['fields', 'include', 'for'] as $parameter) {
                if ($request->query->has($parameter)) {
                    throw ValidationException::withMessages([
                        $parameter => [__('response-selection::validation.selection_without_resource', ['parameter' => $parameter])],
                    ]);
                }
            }

            $tree = SelectionTree::rootFields();
        } elseif ($resolver !== null && $declaration->resourceClass !== null && $request->query->has('for')) {
            [$purpose, $view] = $this->acceptedPurpose($request, $resolver);
            $tree = $this->views->tree($declaration->resourceClass, $view);
        } else {
            $tree = $this->parser->parse($request->query('fields'), $request->query('include'));

            if ($declaration->resourceClass !== null) {
                $this->validator->validate($tree->root(), $declaration->resourceClass);
                $tree = $this->views->expand($tree, $declaration->resourceClass);
            }
        }

        $this->context->start($mode, $tree, $declaration, $purpose);

        try {
            return $next($request);
        } finally {
            $this->context->reset();
        }
    }

    /**
     * The ViewResolver of the application, only looked up when one is bound.
     */
    protected function viewResolver(): ?ViewResolver
    {
        return app()->bound(ViewResolver::class) ? app(ViewResolver::class) : null;
    }

    /**
     * Checks `for` and the parameters that cannot go with it.
     *
     *
     * @throws ValidationException
     *
     * @return array{0: string, 1: string} the purpose and its view
     */
    protected function acceptedPurpose(Request $request, ViewResolver $resolver): array
    {
        $purpose = $request->query->all()['for'] ?? null;

        if (!is_string($purpose) || preg_match('/^[A-Za-z0-9_.-]{1,100}$/D', $purpose) !== 1) {
            throw ValidationException::withMessages(['for' => [__('response-selection::validation.for_invalid')]]);
        }

        $view = $resolver->viewFor($purpose, $request);

        if ($view === null) {
            throw ValidationException::withMessages(['for' => [__('response-selection::validation.for_unknown', ['purpose' => $purpose])]]);
        }

        $messages = [];

        foreach (['fields', 'include'] as $parameter) {
            if ($request->query->has($parameter)) {
                $messages[$parameter] = [__('response-selection::validation.selection_with_for', ['parameter' => $parameter])];
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return [$purpose, $view];
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
