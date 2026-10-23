<?php

// Origine : packages/shared/src/Http/Response/ApiResponse.php du projet d'origine
// from → item, collection → list, paginated → cursorPage, none → noContent, allowedStatuses: → possibleStatuses:.
// ResolveResponseSelection global sur le groupe api : l'appel lu dans le corps de l'action (un seul, XResource::class
// littéral) la déclare ; noContent() seul = action sans corps. Aucun HasMiddleware.

namespace Cases\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * Rend un objet (modèle, objet résultat ou tableau associatif).
     * Statut variable : possibleStatuses: littéral non vide, repris par l'OpenAPI.
     *
     * @param  class-string<ApiResource>  $resourceClass
     * @param  list<int>  $possibleStatuses
     */
    public static function item(mixed $item, string $resourceClass, int $status = 200, array $possibleStatuses = []): JsonResponse
    {
        throw new \LogicException('stub');
    }

    /** Rend une liste non paginée. */
    public static function list(iterable $items, string $resourceClass, int $status = 200): JsonResponse
    {
        throw new \LogicException('stub');
    }

    /** Rend une page par curseur (per_page, next_cursor, prev_cursor). */
    public static function cursorPage(mixed $page, string $resourceClass): JsonResponse
    {
        throw new \LogicException('stub');
    }

    /** Rend une réponse 204 sans contenu. */
    public static function noContent(): Response
    {
        throw new \LogicException('stub');
    }
}
