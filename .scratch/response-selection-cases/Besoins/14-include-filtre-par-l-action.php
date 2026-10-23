<?php

// Besoin : sur une liste filtrée par étiquettes de lots (?stock_label_ids[]=…), l'include stocks de chaque niveau ne
//   montre que les lots qui portent l'une de ces étiquettes ; sans le droit de lister les lots, la clé est omise.
// Fréquence dans le projet d'origine : 1 filtre, 2 routes (InventoryLevelController::index, clé stocks ;
//   StockableController, clé groups de StockableStockLevelResource).
// Avant : include(scope: InventoryStockListingScope) ; le contrôleur posait les étiquettes en attribut de requête
//   (LotLabelFilter::restrictTo()) pour que la scope les relise.
// Maintenant : rien dans le moteur. Le service, qui filtre déjà les niveaux, charge aussi les lots filtrés ;
//   include('stocks') les reprend tels quels (une relation déjà chargée n'est pas rechargée par loadMissing).
//   Sans filtre, le moteur charge tous les lots, seulement si ?include=stocks. LotLabelFilter et le loader disparaissent.
//   Coût accepté : avec un filtre mais sans ?include=stocks, une requête de lots pour rien.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ApiResponse;
use Cases\Api\ResourceDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Origine : packages/catalog/src/Http/Controllers/InventoryLevelController.php:22
final class InventoryLevelController
{
    public function __construct(protected InventoryLevelService $inventoryLevelService) {}

    public function index(ListInventoryLevelsRequest $request)
    {
        $data = $request->validated();

        $levels = $this->inventoryLevelService->listForStockables(
            stockableIds: $data['stockable_ids'],
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            stockLabelIds: $data['stock_label_ids'] ?? [],
        );

        return ApiResponse::cursorPage($levels, InventoryLevelResource::class);
    }
}

// Origine : packages/catalog/src/Services/InventoryLevelService.php:20
final class InventoryLevelService
{
    public function listForStockables(array $stockableIds, int $perPage = 15, ?string $cursor = null, array $stockLabelIds = [])
    {
        Gate::authorize('viewAny', InventoryLevel::class);

        if ($stockLabelIds !== []) {
            // Filtrer sur les lots n'a pas de sens pour qui ne peut pas les voir.
            Gate::authorize('viewAny', InventoryStock::class);
        }

        $labelled = fn ($stocks) => $stocks->labelledWithAny($stockLabelIds);

        return InventoryLevel::query()
            ->whereIn('stockable_id', $stockableIds)
            ->eligibleForCurrentActor()
            ->when($stockLabelIds !== [], fn ($levels) => $levels
                ->whereHas('stocks', $labelled)     // quels niveaux
                ->with(['stocks' => $labelled]))    // quels lots dans chaque niveau
            ->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}

// Origine : packages/catalog/src/Http/Resources/InventoryLevelResource.php:32 (déclaration complète : besoin 17)
final class InventoryLevelResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Sans droit : rien n'est chargé, clé omise. Avec un filtre, les lots déjà chargés par le service sont repris.
        $resource->include('stocks', InventoryStockResource::class,
            when: fn (Request $request) => Gate::allows('viewAny', InventoryStock::class));
    }
}
