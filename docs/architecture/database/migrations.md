# Conventions pour les Migrations

Ce document établit les règles pour la création et la gestion des migrations de base de données, optimisées pour PostgreSQL.

## Principes Généraux

-   **Moteur de base de données :** Le projet utilise exclusivement **PostgreSQL**.
-   **Regroupement :** Les créations de tables logiquement liées (par exemple `users`, `password_reset_tokens`, et `sessions`) doivent être regroupées dans un seul fichier de migration pour plus de clarté.

## Structure des Tables

-   **Noms de table :** Doivent être au pluriel (ex: `users`, `job_postings`).
-   **Clé primaire :** Toujours un UUID. Utiliser `$table->uuid('id')->primary();`.
-   **Clés étrangères :** Utiliser `$table->foreignUuid('user_id')`. Lors de l'ajout d'une contrainte, spécifier explicitement la table : `->constrained('users')`.
-   **Relations polymorphes :** Utiliser `$table->uuidMorphs('commentable');`.
-   **Suppression douce (Soft Deletes) :** Utiliser `$table->softDeletes();` sur les modèles pertinents.
-   **Indexation :** Toutes les clés étrangères (`foreignUuid`) doivent avoir un index (`->index()`). Les colonnes fréquemment utilisées dans les clauses `WHERE` (comme les statuts ou les dates de publication) doivent également être indexées.

## Types de Colonnes

-   **Texte :** Utiliser `text()` pour tous les champs textuels, courts ou longs, afin de bénéficier des optimisations de PostgreSQL. Pour les identifiants courts et de longueur fixe (par exemple, les codes de devise ISO 4217), l'utilisation de `string(length)` est acceptable.
-   **Dates & Heures :** Utiliser `timestamp()` par défaut. `date()` est à éviter, car garder une information d'heure (même à `00:00:00`) est plus flexible pour l'avenir.
-   **JSON :** Toujours utiliser `jsonb()` pour des performances optimales en requête.
-   **Nombres :**
    -   `integer` / `unsignedInteger` pour les nombres entiers standards.
    -   `bigInteger` / `unsignedBigInteger` pour les très grands nombres.
    -   `decimal` pour les valeurs de précision.
-   **Index :** Ajouter des index (`->index()`) sur les colonnes fréquemment interrogées. Utiliser `->unique()` pour les contraintes d'unicité, y compris sur des colonnes composites.

## Contraintes

-   **Nullable vs Default :** Ne jamais utiliser `->nullable()` et `->default()` sur la même colonne. Une colonne est soit nullable, soit elle a une valeur par défaut, mais pas les deux.
