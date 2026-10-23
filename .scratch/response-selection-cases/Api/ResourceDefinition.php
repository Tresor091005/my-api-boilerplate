<?php

// Origine : ResourceManifest du projet d'origine (packages/shared/src/Http/Response/)
// Source unique de la Resource : le rendu est généré depuis elle, clés dans l'ordre de déclaration.
// Champs calculés et includes virtuels sont des membres construits : mêmes dépendances, même rangement par le moteur.

namespace Cases\Api;

use Closure;

final class ResourceDefinition
{
    /**
     * Déclare un champ sous sa clé JSON snake_case.
     * Nu, il lit la colonne de sa clé ; dès que columns:, relations:, fields: ou computedBy: est donné,
     * il ne lit que ce qui est listé (columns: [] seul = ne lit rien).
     * Valeur : computedBy: classe (FieldComputer, groupé) ou closure fn (Model $model, BuiltValues $built) => ...
     * (un modèle, pure, jamais de requête) ; sinon getAttribute(nom) (colonne, cast ou accesseur).
     * relations: chemins nus, chargés entiers (loadMissing) ; une relation déjà chargée est reprise telle quelle.
     * fields: autres champs de cette Resource nécessaires, construits avant, rendus seulement si demandés ;
     * un cycle est refusé au gel de la définition ; un champ du parent redéclaré lève une exception.
     * when:, deux formes :
     * - fn (Request $request): bool (droit, paramètre de requête) : évalué une fois, avant le chargement ;
     *   false = rien n'est chargé ni calculé pour le membre, omis partout ;
     * - fn (Model $model, Request $request): bool : par modèle, après chargement, avant construction ;
     *   un modèle refusé n'est pas calculé et le membre est omis pour lui ; les colonnes lues sont déclarées ici.
     *
     * @param  list<string>|null  $columns
     * @param  list<string>|null  $relations
     * @param  list<string>  $fields
     * @param  class-string<FieldComputer>|Closure|null  $computedBy
     */
    public function field(
        string $name,
        ?array $columns = null,
        ?array $relations = null,
        array $fields = [],
        string|Closure|null $computedBy = null,
        ?Closure $when = null,
    ): self {
        throw new \LogicException('stub');
    }

    /**
     * Déclare un include Eloquent sous sa clé JSON snake_case ; il charge la relation Str::camel(nom), ou relation:.
     * Un filtre, fixe ou lu dans le contexte de la requête, vit dans une relation dédiée du modèle nommée par relation:
     * (relation paresseuse : rien n'est lu avant son appel) ; si le contexte ne convient pas toujours, virtualInclude().
     * Relation polymorphe : un ResourceResolver à la place de la Resource. Clés étrangères et colonnes *_type / *_id
     * sont déduites de la relation. when: comme pour field().
     *
     * @param  class-string<ApiResource>|class-string<ResourceResolver>|ResourceResolver  $resource
     */
    public function include(
        string $name,
        string|ResourceResolver $resource,
        ?string $relation = null,
        ?Closure $when = null,
    ): self {
        throw new \LogicException('stub');
    }

    /**
     * Déclare un include hors Eloquent. loader: classe VirtualIncludeLoader (groupée, [clé parent => enfants])
     * ou closure fn (Model $parent, BuiltValues $built) => enfants (un parent, pure, jamais de requête).
     * columns: / relations: / fields: : ce que le parent doit lire ou construire pour le loader (mêmes règles que field()).
     * asList: true = liste (vide par défaut). Résultat rangé par le moteur, jamais setRelation().
     * when: les deux formes de field() ; un parent refusé n'est pas passé au loader.
     *
     * @param  class-string<ApiResource>|class-string<ResourceResolver>|ResourceResolver  $resource
     * @param  class-string<VirtualIncludeLoader>|Closure  $loader
     * @param  list<string>  $columns
     * @param  list<string>  $relations
     * @param  list<string>  $fields
     */
    public function virtualInclude(
        string $name,
        string|ResourceResolver $resource,
        string|Closure $loader,
        bool $asList = false,
        array $columns = [],
        array $relations = [],
        array $fields = [],
        ?Closure $when = null,
    ): self {
        throw new \LogicException('stub');
    }

    /** Indique si la définition déclare ce champ. */
    public function hasField(string $key): bool
    {
        throw new \LogicException('stub');
    }

    /** Indique si la définition déclare cet include. */
    public function hasInclude(string $name): bool
    {
        throw new \LogicException('stub');
    }
}
