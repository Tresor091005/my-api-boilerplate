<?php

// Besoin : un include virtuel se sert d'un champ calculé du parent (unité d'un niveau tirée du code d'unité
//   calculé depuis le grand livre), même si ce champ n'est pas demandé.
// Fréquence dans le projet d'origine : 0 ; cas illustratif bâti sur LevelLedgerAccountsLoader (le client résout
//   aujourd'hui l'unité par son code). À trancher : garder l'include unit ou non.
// Avant : impossible sans poser le code calculé sur le modèle avant l'include.
// Maintenant : virtualInclude(fields: ['unit_code']) ; le moteur calcule unit_code d'abord (construit, pas rendu),
//   le loader le lit par BuiltValues::get().
// Frontend : nouvel include optionnel, rien ne change sans ?include=unit.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\ResourceDefinition;
use Cases\Api\VirtualIncludeLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

// Origine : packages/catalog/src/Http/Resources/InventoryLevelResource.php:18, :32 et :51
final class InventoryLevelResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Deux champs servis par un calcul (besoin 07).
        $resource->field('available_quantity', columns: ['id'], computedBy: LevelLedgerAccounts::class);
        $resource->field('unit_code', columns: ['id'], computedBy: LevelLedgerAccounts::class);

        // Lots : relation Eloquent ; sur une liste filtrée, le service les a déjà chargés filtrés (besoin 14).
        $resource->include('stocks', InventoryStockResource::class,
            when: fn (Request $request) => Gate::allows('viewAny', InventoryStock::class));
        // Clé JSON item, relation inventoryItem (besoin 10).
        $resource->include('item', InventoryItemResource::class, relation: 'inventoryItem');

        // ?include=unit sans unit_code dans ?fields : unit_code est calculé pour le loader, pas rendu.
        $resource->virtualInclude('unit', UomUnitResource::class, LevelUnits::class, fields: ['unit_code']);
    }
}

// Origine : nouveau, bâti sur LevelLedgerAccountsLoader (packages/catalog/src/Http/Response/)
final class LevelUnits implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        $codes = $parents->map(fn (InventoryLevel $level) => $built->get($level, 'unit_code'))->filter()->unique()->values()->all();
        if ($codes === []) {
            return [];
        }

        // Unités propres à l'organisation de session ; une requête groupée explicite.
        $units = UomUnit::query()->where('organization_id', getTeamOrganization()->id)->whereIn('code', $codes)->get()->keyBy('code');

        return $parents->mapWithKeys(fn (InventoryLevel $level) => [
            $level->getKey() => $units->get($built->get($level, 'unit_code')),
        ])->all();
    }
}
