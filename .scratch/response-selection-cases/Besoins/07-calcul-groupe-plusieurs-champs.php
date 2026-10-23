<?php

// Besoin : une même requête groupée (ou un même appel de service) sert plusieurs champs ; ne calculer que ceux
//   demandés et ne lire que leurs colonnes.
// Fréquence dans le projet d'origine : comptée avec 06 (34 batch:) ; cas cités : LetteringTotalsLoader (3 champs),
//   LevelLedgerAccountsLoader (2), OrderItemCatalogLoader (3), TaxSettingTaxesLoader, PriceTaxConfigurationLoader.
// Avant : batch: [XLoader] répété sur chaque champ avec les mêmes columns: ; le loader posait tous les attributs.
// Maintenant : plusieurs field(computedBy: MêmeClasse) ; compute() reçoit $fields (les champs demandés ou requis)
//   et ne calcule qu'eux : la requête ne sélectionne que leurs agrégats (LetteringTotals), le service ne reçoit que
//   les identifiants utiles (OrderItemCatalog) ; chaque champ déclare ses seules colonnes ; un modèle déjà calculé
//   n'est pas recalculé.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\FieldComputer;
use Cases\Api\ResourceDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Origine : packages/accounting/src/Http/Resources/LetteringResource.php:25
final class LetteringResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->field('lines_count', columns: ['id', 'owner_type', 'owner_id'], computedBy: LetteringTotals::class);
        $resource->field('total_debit', columns: ['id', 'owner_type', 'owner_id'], computedBy: LetteringTotals::class);
        $resource->field('total_credit', columns: ['id', 'owner_type', 'owner_id'], computedBy: LetteringTotals::class);
    }
}

// Origine : packages/accounting/src/Http/Response/LetteringTotalsLoader.php
// Plus de setRawAttributes / syncOriginalAttributes, de filtre instanceof ni de garde array_key_exists.
final class LetteringTotals implements FieldComputer
{
    // Agrégat SQL de chaque champ. Seuls ceux des champs demandés partent dans le select : ?fields=lines_count
    //   ne calcule pas les deux sommes. Pas d'injection : les expressions sont des constantes et $fields ne contient
    //   que des clés déclarées avec computedBy: LetteringTotals (un nom demandé inconnu n'y arrive jamais).
    private const AGGREGATES = [
        'lines_count' => 'count(*)',
        'total_debit' => "coalesce(sum(case when direction = 'debit' then amount else 0 end), 0)",
        'total_credit' => "coalesce(sum(case when direction = 'credit' then amount else 0 end), 0)",
    ];

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $select = collect($fields)->map(fn (string $field) => self::AGGREGATES[$field].' as '.$field)->implode(', ');
        $values = [];

        // Une requête par livre : une page contient les lettrages d'un seul livre.
        foreach ($models->groupBy(fn (Lettering $l) => $l->owner_type.'|'.$l->owner_id) as $group) {
            $first = $group->first();
            $totals = DB::table('accounting_journal_lines')
                ->where('owner_type', $first->owner_type)
                ->where('owner_id', $first->owner_id)
                ->whereIn('lettering_id', $group->pluck('id')->all())
                ->groupBy('lettering_id')
                ->selectRaw('lettering_id, '.$select)
                ->get()->keyBy('lettering_id');

            foreach ($group as $lettering) {
                // Lettrage sans ligne : absent du résultat, ses totaux valent 0.
                $row = $totals->get($lettering->id);
                $values[$lettering->getKey()] = collect($fields)->mapWithKeys(fn (string $field) => [
                    $field => $field === 'lines_count'
                        ? (int) ($row->lines_count ?? 0)
                        : /* même conversion des montants qu'avant */ (int) ($row->{$field} ?? 0),
                ])->all();
            }
        }

        return $values;
    }
}

// Origine : packages/order/src/Http/Resources/OrderItemResource.php:15 et :25
final class OrderItemResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Montant en unité mineure et exposant (besoin 03) ; même forme pour les 7 totaux d'OrderResource.
        $resource->field('unit_price', columns: ['unit_price', 'currency_exponent'],
            computedBy: fn (OrderItem $item) => fromMinorUnit($item->unit_price, $item->currency_exponent));
        $resource->field('line_total', columns: ['line_total', 'currency_exponent'],
            computedBy: fn (OrderItem $item) => fromMinorUnit($item->line_total, $item->currency_exponent));

        // À trancher : vérifier que catalogTitle() / catalogOptions() ne lisent pas l'unité (sinon stockable_unit_id ici).
        $resource->field('title', columns: ['stockable_id'], computedBy: OrderItemCatalog::class);
        $resource->field('options', columns: ['stockable_id'], computedBy: OrderItemCatalog::class);
        $resource->field('uom_unit_name', columns: ['stockable_unit_id'], computedBy: OrderItemCatalog::class);
    }
}

// Origine : packages/order/src/Http/Response/OrderItemCatalogLoader.php
// OrderDocumentPresenter (19) appelle le service catalogue lui-même, pas cette classe.
final class OrderItemCatalog implements FieldComputer
{
    public function __construct(protected CatalogInterface $catalog) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        // stockable_unit_id n'est chargé que si uom_unit_name est demandé : ne pas le lire sinon.
        $withUnit = in_array('uom_unit_name', $fields, true);
        $withStockable = array_diff($fields, ['uom_unit_name']) !== [];

        $stockableIds = $withStockable ? $models->map(fn (OrderItem $i) => $i->getAttribute('stockable_id'))->filter()->unique()->values()->all() : [];
        $unitIds = $withUnit ? $models->map(fn (OrderItem $i) => $i->getAttribute('stockable_unit_id'))->filter()->unique()->values()->all() : [];
        // Contexte de requête lu ici : la classe est résolue par le conteneur à chaque requête.
        $details = $this->catalog->describeOrderStockables($stockableIds, $unitIds, getTeamOrganization()->id);

        return $models->mapWithKeys(function (OrderItem $item) use ($details, $fields, $withUnit, $withStockable) {
            $stockable = $withStockable ? $details['stockables'][$item->getAttribute('stockable_id')] ?? null : null;
            $unit = $withUnit ? $details['units'][$item->getAttribute('stockable_unit_id')] ?? null : null;

            $values = [];
            foreach ($fields as $field) {
                $values[$field] = match ($field) {
                    'title' => /* même règle que catalogTitle() avec $stockable */ null,
                    'options' => (object) (/* même règle que catalogOptions() avec $stockable */ []),
                    'uom_unit_name' => /* nom de $unit */ null,
                };
            }

            return [$item->getKey() => $values];
        })->all();
    }
}

// Origine : packages/catalog/src/Http/Response/LevelLedgerAccountsLoader.php
// Déclaré par InventoryLevelResource (17) : available_quantity et unit_code, columns: ['id'] (compte d'external_id = id du niveau).
final class LevelLedgerAccounts implements FieldComputer
{
    public function __construct(protected LedgerInterface $ledger) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        // À trancher : réutiliser un niveau déjà amorcé par le métier (hasResolvedLedgerAccount()) ou toujours recalculer.
        $accounts = $this->ledger->retrieveAccountsByExternalIds($models->pluck('id')->unique()->values()->all());

        return $models->mapWithKeys(function (InventoryLevel $level) use ($accounts, $fields) {
            $account = $accounts->get($level->id);

            // Mêmes dérivations que les accesseurs available_quantity / unit_code / unit_exponent du modèle.
            return [$level->getKey() => Arr::only([
                'available_quantity' => $account === null ? null : fromMinorUnit(/* quantité du compte */ 0, /* exposant de l'unité */ 0),
                'unit_code' => /* code d'unité du compte */ null,
            ], $fields)];
        })->all();
    }
}
