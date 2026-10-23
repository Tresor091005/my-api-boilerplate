<?php

// Origine : packages/shared/src/Http/Response/ApiResource.php:240 du projet d'origine
// manifest() → define() ; plus de toArray() dans les Resources : le moteur rend depuis la définition.
// unmigratedRelation() et le mode sans sélection supprimés : rendue hors ApiResponse = JsonResource.
// Lazy loading interdit pendant le chargement et le rendu, dans tous les environnements (Model::preventLazyLoading()).

namespace Cases\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    /**
     * Seule méthode d'une Resource : remplit la définition (parent::define() d'abord dans une Resource héritée).
     * Le moteur la crée, la fige (doublons, cycles de fields:) et la met en cache par classe.
     */
    protected static function define(ResourceDefinition $resource): void
    {
    }

    /** Renvoie la définition figée de la classe (construite une fois via define()). */
    final public static function definition(): ResourceDefinition
    {
        throw new \LogicException('stub');
    }

    /**
     * Rendu généré depuis la définition : clés dans l'ordre de déclaration, membres non demandés omis,
     * membres refusés par when: omis pour ce modèle. Valeur d'un champ : computedBy: (classe ou closure),
     * sinon getAttribute(clé) ; include : relation chargée ou résultat du loader rangé par le moteur.
     */
    final public function toArray(Request $request): array
    {
        throw new \LogicException('stub');
    }
}
