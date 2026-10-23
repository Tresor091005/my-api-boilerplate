<?php

// Origine : nouveau, aucun équivalent dans le projet d'origine (les loaders lisaient des attributs posés sur le modèle)
// Lecture, par un membre construit, des champs calculés qu'il déclare dans fields: ; fourni par le moteur à chaque appel
// (classe ou closure de computedBy:, loader de virtualInclude()).

namespace Cases\Api;

use Illuminate\Database\Eloquent\Model;

/**
 * Valeurs déjà calculées des champs listés dans le fields: du membre en cours de construction.
 * Un modèle pour lequel un de ces champs manque n'est pas passé au membre (absence propagée par fields:).
 * Un champ non calculé de fields: se lit directement sur le modèle : ses colonnes et relations sont chargées.
 */
interface BuiltValues
{
    /**
     * Valeur finale du champ calculé pour un modèle reçu par le membre.
     * Exception si le champ n'est pas dans le fields: du membre ou n'est pas un champ computedBy:.
     */
    public function get(Model $model, string $field): mixed;
}
