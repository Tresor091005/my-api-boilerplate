# Conventions pour les Modèles Eloquent

Ce document établit les règles et conventions pour la création et la gestion des modèles Eloquent dans l'application, afin d'assurer cohérence, maintenabilité et conformité avec les bonnes pratiques.

## Principes Généraux

-   **Emplacement et Namespace :** Les modèles du module `catalog` doivent être placés dans `app-modules/catalog/src/Models/` et utiliser le namespace `Lahatre\Catalog\Models`.
-   **Classe de Base :** Tous les modèles doivent étendre `Illuminate\Database\Eloquent\Model`.
-   **Traits Communs :** Utiliser le trait `Lahatre\Shared\Traits\SharedTraits`. Ce trait inclut `Illuminate\Database\Eloquent\Concerns\HasUuids` (pour les clés primaires UUID) et `Illuminate\Database\Eloquent\Factories\HasFactory` (pour les usines de modèles).

## Définitions des Propriétés du Modèle

-   **`$table` :** Toujours définir explicitement la propriété `$table` pour spécifier le nom de la table de base de données associée au modèle (par exemple, `protected $table = 'catalog_currencies';`). Les noms de table doivent être au pluriel.
-   **`$primaryKey` et `$incrementing` :**
    -   Par défaut, toutes les tables devraient utiliser un UUID comme clé primaire (`id`). Dans ce cas, il n'est pas nécessaire de définir `$primaryKey` ou `$incrementing`.
    -   Si une table utilise une colonne différente de `id` comme clé primaire (par exemple, `code` pour `catalog_currencies`), ou si la clé primaire n'est pas un UUID auto-incrémenté, vous devez explicitement définir `protected $primaryKey = 'votre_cle_primaire';` et `public $incrementing = false;`.
-   **`$fillable` :** Définir explicitement toutes les colonnes qui peuvent être assignées massivement (mass assignable).
-   **`$casts` :**
    -   Toutes les colonnes du modèle doivent être définies dans la propriété `$casts`.
    -   **Dates/Heures :** Utiliser `immutable_datetime` pour toutes les colonnes de date et d'heure (`created_at`, `updated_at`, `active_from`, `active_to`, etc.) afin de garantir l'utilisation d'objets `CarbonImmutable`.
    -   **UUIDs :** Toutes les colonnes UUID (clés primaires `id` et clés étrangères `*_id`) doivent être castées en `string`.
    -   **Nombres :** Caster les colonnes numériques (`integer`, `bigInteger`, `decimal`, etc.) en types PHP appropriés (`integer`, `float`, `string` pour les grands nombres décimaux).
    -   **Booléens :** Caster les colonnes booléennes en `boolean`.
    -   **Autres chaînes :** Les autres colonnes de type texte qui ne sont ni UUID ni dates/heures peuvent être castées en `string` pour une explicitation maximale.

## Relations Eloquent

-   **Explicitation :** Toujours définir les relations de manière explicite, en spécifiant les clés étrangères, les clés locales et les noms de table de liaison (si applicable). La clarté est essentielle.
    -   Exemple `belongsTo` : `return $this->belongsTo(Category::class, 'parent_id', 'id');`
    -   Exemple `belongsToMany` : `return $this->belongsToMany(Tag::class, 'catalog_product_tags', 'product_id', 'tag_id')->using(ProductTag::class)->withTimestamps();`
-   **Modèles Pivot :** Pour les tables intermédiaires (`pivot tables`) qui contiennent des colonnes supplémentaires (comme `timestamps()`), toujours créer un modèle pivot dédié qui étend `Illuminate\Database\Eloquent\Relations\Pivot`.
    -   Ces modèles pivots doivent également utiliser `SharedTraits` et définir `$table` ainsi que `$casts` pour toutes leurs colonnes.
    -   Les relations `belongsToMany` doivent utiliser la méthode `->using(VotreModelePivot::class)` et `->withTimestamps()` si la table pivot a des timestamps.

## Style de Code

-   **Pas de Commentaires Explicatifs :** Éviter les commentaires qui décrivent ce que fait le code. Le code doit être auto-descriptif. Les commentaires sont réservés aux explications complexes ou aux raisons derrière une décision technique non évidente.
-   **Conventions de Nommage :** Respecter les conventions de nommage PSR-12 et les conventions Laravel (par exemple, noms de modèles au singulier, noms de tables au pluriel).
