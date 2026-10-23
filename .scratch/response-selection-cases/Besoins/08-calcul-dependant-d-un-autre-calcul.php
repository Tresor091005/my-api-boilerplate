<?php

// Besoin : un champ calculé se sert d'un autre champ (calculé ou non) de la même Resource, même quand celui-ci
//   n'est pas demandé, sans refaire ses requêtes.
// Fréquence dans le projet d'origine : 1 chaîne (AssetResource : metadata → occupied_volume) ;
//   AssetOccupiedVolumesLoader rechargeait les unités déjà chargées par AssetMeasurementUnitsLoader.
// Avant : un champ à deux loaders batch: ; le second lisait les attributs posés par le premier sur le modèle.
//   Un seul loader de volumes servait occupied_volume et fill_rate (volume de référence mémorisé sur le modèle).
// Maintenant : fields: ['autre'] ; le moteur construit d'abord la dépendance (construite, pas rendue si non demandée),
//   ordre fixé par fields:, cycle refusé au gel ; la valeur calculée se lit par BuiltValues::get() ;
//   un modèle pour lequel une dépendance manque n'est pas passé au calcul et le champ est omis pour lui.
//   Deux champs servis par la même classe ne se déclarent pas dans fields: : la classe calcule en interne ce que
//   chacun utilise (ici le volume de référence), une seule requête quels que soient les champs demandés.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\BuiltValues;
use Cases\Api\FieldComputer;
use Cases\Api\ResourceDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// Origine : packages/catalog/src/Http/Resources/AssetResource.php:25 (étend AssetSummaryResource, besoin 18)
final class AssetResource extends AssetSummaryResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        // Relation lue par un champ (besoin 04).
        $resource->field(
            'company_ids',
            relations: ['companies'],
            computedBy: fn (Asset $asset) => $asset->companies->sortBy('id')->pluck('company_id')->values()->all(),
        );
        // AssetMetadata (cast de la carte de métadonnées, unités résolues) : non détaillée ici.
        $resource->field('metadata', computedBy: AssetMetadata::class);

        // Clé absente hors emplacement : when: par modèle (après chargement), lit type, déclaré dans columns:.
        $isLocation = fn (Asset $asset, Request $request) => $asset->type === AssetType::LOCATION->value;
        // occupied_volume s'exprime dans l'unité de volume résolue par metadata ; fill_rate ne lit que fillable_volume brut.
        $resource->field('occupied_volume', columns: ['type'], relations: ['metadata'], fields: ['metadata'],
            computedBy: LocationVolumes::class, when: $isLocation);
        $resource->field('fill_rate', columns: ['type'], relations: ['metadata'],
            computedBy: LocationVolumes::class, when: $isLocation);
    }
}

// Origine : packages/catalog/src/Http/Response/AssetOccupiedVolumesLoader.php, AssetResource::occupiedVolume() / fillRate()
// Une classe pour les deux champs, comme le loader d'origine : une requête de volumes, que l'on demande occupied_volume,
//   fill_rate ou les deux ; les unités viennent de metadata (fields:), plus de requête d'unités en double.
final class LocationVolumes implements FieldComputer
{
    private const FILL_RATE_SCALE = 6;

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        // $models : emplacements seulement (when:) ; pour occupied_volume, ceux dont metadata existe.
        $volumes = InventoryLevel::query()
            ->join('catalog_assets', /* ... */)->join('catalog_inventory_stocks', /* ... */)->join('catalog_stockables', /* ... */)
            ->whereIn('catalog_inventory_levels.asset_id', $models->map(fn (Asset $a) => $a->getKey())->unique()->values()->all())
            ->whereColumn('catalog_inventory_levels.organization_id', 'catalog_assets.organization_id')
            ->where('catalog_inventory_stocks.quantity', '>', 0)
            ->groupBy('catalog_inventory_levels.asset_id')
            ->selectRaw('catalog_inventory_levels.asset_id as asset_id, COALESCE(SUM(COALESCE(catalog_stockables.volume, 0) * catalog_inventory_stocks.quantity), 0) as occupied_volume')
            ->toBase()->pluck('occupied_volume', 'asset_id');

        return $models->mapWithKeys(function (Asset $asset) use ($volumes, $fields, $built) {
            // Volume de référence : la base des deux champs, jamais la valeur formatée d'occupied_volume.
            $reference = (string) ($volumes->get($asset->getKey()) ?? 0);

            return [$asset->getKey() => collect($fields)->mapWithKeys(fn (string $field) => [$field => match ($field) {
                'occupied_volume' => $this->occupiedVolume($reference, $built->get($asset, 'metadata')),
                'fill_rate' => $this->fillRate($reference, $asset->rawMetadataMap()['fillable_volume'] ?? null),
            }])->all()];
        })->all();
    }

    private function occupiedVolume(string $reference, array $metadata): int|float|null
    {
        // À trancher : forme exacte de la valeur de metadata (unité de volume résolue).
        $unit = $metadata['volume_unit'] ?? null;

        return $unit ? MeasurementValue::formatFromReference($reference, $unit) : null;
    }

    private function fillRate(string $reference, mixed $fillableVolume): int|float|null
    {
        if ($fillableVolume === null) {
            return null;
        }
        if (bccomp((string) $fillableVolume, '0', 0) !== 1) {
            return 0;
        }

        return round((float) bcmul(bcdiv($reference, (string) $fillableVolume, self::FILL_RATE_SCALE), '100', self::FILL_RATE_SCALE), 2);
    }
}
