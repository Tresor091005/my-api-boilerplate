<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Lahatre\Shared\Enums\ResponseShape;
use Lahatre\Shared\Exceptions\ResponseSelectionException;

/**
 * Response factory of the actions ResolveResponseSelection knows from their source. The factory
 * fixes the JSON shape, so the runtime payload and the OpenAPI documentation always agree:
 * - item() renders one model as {data: {...}};
 * - list() renders a list of models as {data: [...]};
 * - cursorPage() renders a cursor page as {data: [...], meta: {per_page, next_cursor, prev_cursor}}.
 * Every item is an Eloquent model; anything else throws before loading. A result built in code
 * (value object, report) renders through a plain JsonResource, without selection.
 *
 * Each call must match the declaration of the matched action, read statically and kept in the
 * ResponseContext: same factory and Resource, or noContent() for an action that may answer empty.
 *
 * `none` → 204 without touching the result. `resource` → runs the SelectionLoader, then renders.
 * Load and render run with missing-attribute protection, restored afterwards, and with lazy loading
 * prevented on every model the response reaches through loaded relations, in every environment: a
 * column the definition does not declare, or a relation read without being loaded, throws unless
 * the application registers the matching violation handler. Eloquent exempts a model that is not
 * persisted or was created in this request (a POST rendering what it created): it still lazy-loads,
 * so the relations it needs are declared in `relations:` or loaded beforehand. Each model gets its
 * own lazy-loading flag back after rendering.
 */
final class ApiResponse
{
    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, int>  $possibleStatuses  declared possibilities when the status depends on the result
     */
    public static function item(mixed $result, string $resourceClass, int $status = 200, array $possibleStatuses = []): JsonResponse|Response
    {
        if ($result instanceof CursorPaginator || $result instanceof Paginator) {
            throw ResponseSelectionException::itemReceivedPage();
        }

        if ($result instanceof Enumerable || (is_array($result) && array_is_list($result) && $result !== [])) {
            throw ResponseSelectionException::itemReceivedList();
        }

        if ($result === null) {
            throw ResponseSelectionException::itemRequiresResult();
        }

        $result = self::assertItem($result, $resourceClass, 'item');

        return self::respond($result, $resourceClass, ResponseShape::Item, $status, $possibleStatuses, fn (SelectionNode $selection) => [
            'data' => (new $resourceClass($result))->attachSelection($selection),
        ]);
    }

    /**
     * @param  Collection<int, Model>|array<int, Model>  $items
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, int>  $possibleStatuses  declared possibilities when the status depends on the result
     */
    public static function list(Collection|array $items, string $resourceClass, int $status = 200, array $possibleStatuses = []): JsonResponse|Response
    {
        $items = self::toList($items, $resourceClass, 'list');

        return self::respond($items, $resourceClass, ResponseShape::List, $status, $possibleStatuses, fn (SelectionNode $selection) => [
            'data' => ApiResource::attachSelectionToEach($resourceClass::collection($items), $selection),
        ]);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, int>  $possibleStatuses  declared possibilities when the status depends on the result
     */
    public static function cursorPage(CursorPaginator $page, string $resourceClass, int $status = 200, array $possibleStatuses = []): JsonResponse|Response
    {
        $items = self::toList(collect($page->items()), $resourceClass, 'cursorPage');

        return self::respond($items, $resourceClass, ResponseShape::CursorPage, $status, $possibleStatuses, fn (SelectionNode $selection) => [
            'data' => ApiResource::attachSelectionToEach($resourceClass::collection($items), $selection),
            'meta' => [
                'per_page'    => $page->perPage(),
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ],
        ]);
    }

    /**
     * 204 from the action: allowed when its declaration may answer empty.
     */
    public static function noContent(): Response
    {
        self::assertDeclared('noContent()');

        return response()->noContent();
    }

    /**
     * Normalizes the items of a list response: string keys would render a JSON object, and integer
     * keys left by filter() are re-indexed, as legacy collections did.
     *
     * @return EloquentCollection<int, Model>
     */
    private static function toList(Collection|array $items, string $resourceClass, string $factory): EloquentCollection
    {
        $items = collect($items);

        if ($items->keys()->contains(fn ($key) => is_string($key))) {
            throw ResponseSelectionException::listHasStringKeys($factory);
        }

        return new EloquentCollection($items->map(fn ($item) => self::assertItem($item, $resourceClass, $factory))->values()->all());
    }

    private static function assertItem(mixed $item, string $resourceClass, string $factory): Model
    {
        if ($item instanceof JsonResource) {
            throw ResponseSelectionException::resourceGivenAsResult($factory);
        }

        if (!$item instanceof Model) {
            throw ResponseSelectionException::notAModel($resourceClass, get_debug_type($item));
        }

        return $item;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, int>  $possibleStatuses
     * @param  Closure(SelectionNode): mixed  $render
     */
    private static function respond(Model|EloquentCollection $result, string $resourceClass, ResponseShape $shape, int $status, array $possibleStatuses, Closure $render): JsonResponse|Response
    {
        $context = app(ResponseContext::class);
        self::assertDeclared("{$shape->factory()}({$resourceClass}::class)", $shape, $resourceClass);

        if (!is_subclass_of($resourceClass, ApiResource::class)) {
            throw ResponseSelectionException::resourceClassInvalid($resourceClass);
        }

        foreach ([$status, ...$possibleStatuses] as $code) {
            if (!is_int($code) || $code < 200 || $code >= 300 || in_array($code, [204, 205], true)) {
                throw ResponseSelectionException::statusWithoutContent($code);
            }
        }

        if ($possibleStatuses !== [] && !in_array($status, $possibleStatuses, true)) {
            throw ResponseSelectionException::statusNotPossible($status);
        }

        if (!$context->wantsResource()) {
            return response()->noContent();
        }

        $selection = $context->selection()->root();
        $loader = app(SelectionLoader::class);
        $preventing = Model::preventsAccessingMissingAttributes();
        Model::preventAccessingMissingAttributes();

        try {
            $loader->load($result, $resourceClass, $selection);

            return response()->json($render($selection), $status);
        } finally {
            $loader->release();
            Model::preventAccessingMissingAttributes($preventing);
        }
    }

    /**
     * The call matches the declaration of the matched action: same shape and Resource, or, for
     * noContent() (null shape), a declaration that may answer empty. Without a started context, the
     * action was not declared: a stale declaration cache, or a call outside the action body.
     *
     * @param  string  $call  the call as messages show it
     */
    private static function assertDeclared(string $call, ?ResponseShape $shape = null, string $resourceClass = ''): void
    {
        $context = app(ResponseContext::class);
        $declaration = $context->declaration();
        $route = request()->route();
        $action = $route instanceof Route ? $route->getActionName() : 'unknown action';

        if (!$context->isActive() || $declaration === null) {
            throw ResponseSelectionException::declarationMissing($action);
        }

        $matches = $shape === null
            ? $declaration->canBeEmpty
            : $declaration->shape === $shape && strcasecmp($declaration->resourceClass ?? '', $resourceClass) === 0;

        if (!$matches) {
            throw ResponseSelectionException::declarationMismatch($action, $declaration->describe(), $call);
        }
    }
}
