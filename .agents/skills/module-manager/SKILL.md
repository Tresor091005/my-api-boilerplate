---
name: module-manager
description: Gestionnaire de modules Laravel (Internachi/Modular). Gère le cycle de vie, les commandes Artisan modulaires (make:module, make:controller --module) et les namespaces Lahatre.
---

# Skill: Laravel Module Manager

Ce skill gère le cycle de vie des modules dans l'architecture `app-modules/` avec le namespace `Lahatre`.

## 1. Création d'un Nouveau Module

Lorsqu'un nouveau module est demandé :
1.  **Génération** : `php artisan make:module <name>`
2.  **Enregistrement Composer** : `composer update lahatre/<name>`
3.  **Vérification** : S'assurer que le dossier `app-modules/<name>` contient la structure standard (`src/`, `routes/`, `database/`, `resources/`).

## 2. Génération de Fichiers dans un Module

Toutes les commandes `make` **doivent** inclure l'option `--module`.

### Exemples :
-   **Controller** : `php artisan make:controller MyController --module=<name>`
-   **Model** : `php artisan make:model MyModel --module=<name>`
-   **Migration** : `php artisan make:migration create_table_name --module=<name>`
-   **Form Request** : `php artisan make:request MyRequest --module=<name>`
-   **Data** : `php artisan make:class Data/MyData --module=<name>` puis implémenter systématiquement `::fromArray()`.

## 3. Base de Données & Seeders

Le seeding doit être ciblé pour éviter de polluer les autres modules.

-   **Seeder Principal** : `php artisan db:seed --module=<name>`
    -   Appelle `Lahatre\<Name>\Database\Seeders\DatabaseSeeder`.
-   **Seeder Spécifique** : `php artisan db:seed --class=MySeeder --module=<name>`
    -   Appelle `Lahatre\<Name>\Database\Seeders\MySeeder`.

## 4. Conventions de Consommation

-   **Traductions** : Toujours utiliser le préfixe `module::`.
    -   Exemple : `__('<name>::messages.welcome')`.
-   **Routes** : Les routes sont préfixées automatiquement (vérifier dans `routes/`).
-   **Policies** : Placées dans `src/Policies/` et résolues par convention.

## 5. Workflow de Validation
Après chaque création :
1.  Vérifier l'autoloader : `composer dump-autoload`.
2.  Tester la découverte : `php artisan route:list --path=v1/<name>`.
