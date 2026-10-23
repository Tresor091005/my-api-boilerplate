<?php

// Origine : IncludeLoader::attach(Collection $parents, string $name) du projet d'origine
// Plus de $name ni de setRelation() / aplatissement à la main : une classe par include, une valeur par clé de parent,
// rangée par le moteur (jamais sur le modèle) et rendue par lui.
// Enfant lu sur le parent sans requête : loader: fn (Model $parent, BuiltValues $built) => ... (closure pure).

namespace Cases\Api;

use Illuminate\Support\Collection;

interface VirtualIncludeLoader
{
    /**
     * Charge l'include virtuel pour tous les parents du nœud, en un nombre borné de requêtes explicites.
     * $parents : parents retenus par le when: de l'include.
     * $built lit les champs calculés du parent listés dans le fields: de virtualInclude() (déjà construits).
     *
     * @return array<array-key, mixed> valeur par clé de parent ; parent absent = valeur vide
     *                                  (null, ou liste vide avec asList: true)
     */
    public function load(Collection $parents, BuiltValues $built): array;
}
