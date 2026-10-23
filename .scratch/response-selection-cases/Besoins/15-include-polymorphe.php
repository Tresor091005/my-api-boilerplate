<?php

// Besoin : inclure une relation polymorphe dont chaque enfant est rendu par la Resource de son type
//   (sujet d'un stockable : variante, lot ; type sans Resource rendu null).
// Fréquence dans le projet d'origine : 1 include polymorphe (StockableResource.subject, StockableRegistry).
// Avant : include polymorphe avec un ResourceResolver (StockableRegistry) ; même contrat.
// Maintenant : include('subject', StockableRegistry::class) : le ResourceResolver à la place de la Resource ;
//   MorphTo chargé par Eloquent, subject_type / subject_id déduits ; ?fields / ?include validés sur resources().
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;
use Cases\Api\ResourceResolver;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

// Origine : packages/catalog/src/Http/Resources/StockableResource.php:23 et :40
final class StockableResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Champs lus sur le sujet selon son type (besoin 05).
        $resource->field('image_url', relations: ['subject'], computedBy: StockableSubjectFields::class);
        $resource->field('title', relations: ['subject'], computedBy: StockableSubjectFields::class);

        // À trancher (#13) : ?include=subject.xxx quand xxx n'existe que sur une des Resources possibles ; proposé : ignoré.
        $resource->include('subject', StockableRegistry::class);
    }
}

// Origine : packages/catalog/src/Support/StockableRegistry.php:19 ; extrait : seules les méthodes du résolveur.
final class StockableRegistry implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        $definition = $this->definitions()[$model::class] ?? null;
        if (! $definition) {
            throw new InvalidArgumentException(/* message traduit : modèle non enregistré */);
        }

        // null pour un type sans Resource (lots).
        return $definition['resource'];
    }

    public function resources(): array
    {
        return collect($this->definitions())->pluck('resource')->filter()->unique()->values()->all();
    }
}
