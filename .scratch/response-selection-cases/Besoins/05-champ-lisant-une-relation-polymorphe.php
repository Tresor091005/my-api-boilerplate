<?php

// Besoin : rendre une valeur lue sur une relation polymorphe (MorphTo), dont les relations à charger dépendent
//   du type du modèle lié (titre d'un stockable : variante avec ses options, lot ; image de la variante seulement).
// Fréquence dans le projet d'origine : 4 Resources (StockableListResource:19, StockableResource:23,
//   VariantStockableResource:26/41) ; seul loadMorph() réel ailleurs : StockableTaxContextBuilder:364, hors rendu.
// Avant : columns + loads + batch: [StockableSubject*Loader] ; la proposition field(morph:) + MorphRelations est abandonnée.
// Maintenant : relations: ['subject'] (MorphTo nu) + computedBy: classe qui charge explicitement, une requête par type,
//   les relations du registre pour les seuls champs demandés (requêtes groupées explicites permises dans une classe
//   de construction ; jamais de lazy loading). Carte des relations par type laissée au registre.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\FieldComputer;
use Cases\Api\ResourceDefinition;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

// Origine : packages/catalog/src/Http/Resources/StockableListResource.php:19
// Même forme : StockableResource (15), VariantStockableResource.
final class StockableListResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // subject_type / subject_id : colonnes du MorphTo, déclarées par relations: ['subject'].
        $resource->field('title', relations: ['subject'], computedBy: StockableSubjectFields::class);
        $resource->field('options', relations: ['subject'], computedBy: StockableSubjectFields::class);
        // La requête d'images ne part que si image_url est demandé.
        $resource->field('image_url', relations: ['subject'], computedBy: StockableSubjectFields::class);
    }
}

// Origine : StockableSubjectTitlesLoader et StockableRegistry::loadRelationsForTitleGeneration (packages/catalog/)
final class StockableSubjectFields implements FieldComputer
{
    public function __construct(protected StockableRegistry $registry) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $withTitle = array_intersect($fields, ['title', 'options']) !== [];
        $withImage = in_array('image_url', $fields, true);

        // Relations par type, chargées sur les sujets déjà chargés : une requête par type et par relation.
        foreach ($models->pluck('subject')->filter()->groupBy(fn (Model $subject) => $subject::class) as $class => $subjects) {
            $relations = [
                // À ajouter au registre : carte classe => title_relations (loadRelationsForTitleGeneration reste pour le métier).
                ...($withTitle ? $this->registry->titleRelations()[$class] ?? [] : []),
                ...($withImage && $class === ProductVariant::class ? ['image'] : []),
            ];
            if ($relations !== []) {
                (new EloquentCollection($subjects->all()))->loadMissing($relations);
            }
        }

        return $models->mapWithKeys(fn (Stockable $stockable) => [$stockable->getKey() => collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => match ($field) {
                'title' => $stockable->subject?->title,
                'options' => /* noms et valeurs d'options du sujet, inchangé */ [],
                'image_url' => match (true) {
                    $stockable->subject instanceof ProductVariant => $stockable->subject->image?->src_url,
                    $stockable->subject instanceof Bundle => $stockable->subject->image_url,
                    default => null,
                },
            }])->all()])->all();
    }
}
