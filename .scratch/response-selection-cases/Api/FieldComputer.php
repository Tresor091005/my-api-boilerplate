<?php

// Origine : BatchLoader du projet d'origine (packages/*/src/Http/Response/), un loader pouvant servir plusieurs champs
// Le moteur range les valeurs, jamais sur le modèle ; un champ absent pour un modèle est omis ;
// un null voulu se renvoie explicitement ; conversions et valeurs par défaut se font ici.
// Calcul d'un seul modèle sans requête : computedBy: fn (Model $model, BuiltValues $built) => ... (closure pure).

namespace Cases\Api;

use Illuminate\Support\Collection;

interface FieldComputer
{
    /**
     * Calcule les champs $fields pour tous les modèles du nœud, en un nombre borné de requêtes explicites.
     * $models : modèles retenus par le when: de chaque champ (les refusés ne sont pas calculés).
     * $fields : clés servies par la classe et demandées à ce nœud, ou requises par le fields: d'un autre membre.
     * Seules les dépendances de ces champs sont chargées : calculer une autre clé lirait des colonnes absentes (erreur).
     * Le moteur retient par modèle les champs déjà calculés : un modèle atteint deux fois ne calcule que les manquants.
     * $built lit les champs calculés déclarés dans le fields: de ces champs (déjà construits, ordre fixé par le moteur).
     *
     * @param  list<string>  $fields
     * @return array<array-key, array<string, mixed>> valeurs finales par clé de modèle, puis par clé de champ de $fields
     */
    public function compute(Collection $models, array $fields, BuiltValues $built): array;
}
