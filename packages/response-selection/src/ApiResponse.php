<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

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
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Rendering\SelectionLoader;
use Lahatre\ResponseSelection\Selection\SelectionNode;

/**
 * Turns the Eloquent models an action returns into its JSON response, always in the shape the API
 * docs announce. Reading a column or relation the Resource did not declare fails instead of querying.
 */
final class ApiResponse
{
    /**
     * Responds with one model, as `{data: {...}}`.
     *
     * @param  mixed  $result  the model to render
     * @param  class-string<ApiResource>  $resourceClass  the Resource that renders it
     * @param  int  $status  HTTP status of the response
     * @param  array<int, int>  $possibleStatuses  every status the action may answer with, when it varies
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
     * Responds with a list of models, as `{data: [...]}`.
     *
     * @param  Collection<int, covariant Model>|array<int, Model>  $items  the models to render
     * @param  class-string<ApiResource>  $resourceClass  the Resource that renders each model
     * @param  int  $status  HTTP status of the response
     * @param  array<int, int>  $possibleStatuses  every status the action may answer with, when it varies
     */
    public static function list(Collection|array $items, string $resourceClass, int $status = 200, array $possibleStatuses = []): JsonResponse|Response
    {
        $items = self::toList($items, $resourceClass, 'list');

        return self::respond($items, $resourceClass, ResponseShape::List, $status, $possibleStatuses, fn (SelectionNode $selection) => [
            'data' => ApiResource::attachSelectionToEach($resourceClass::collection($items), $selection),
        ]);
    }

    /**
     * Responds with one page of models, as `{data: [...], meta: {...}}` with the cursors in `meta`.
     *
     * @param  CursorPaginator  $page  the page returned by cursorPaginate()
     * @param  class-string<ApiResource>  $resourceClass  the Resource that renders each model
     * @param  int  $status  HTTP status of the response
     * @param  array<int, int>  $possibleStatuses  every status the action may answer with, when it varies
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
     * Responds with an empty 204, for an action declared as possibly answering with no content.
     */
    public static function noContent(): Response
    {
        self::assertDeclared('noContent()');

        return response()->noContent();
    }

    /**
     * Turns the items into a plain list, so they always render as a JSON array.
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
        self::assertDeclared("{$shape->value}({$resourceClass}::class)", $shape, $resourceClass);

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
     * Checks that this call matches what the action declares it returns.
     *
     * @param  string  $call  the call, as error messages show it
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
