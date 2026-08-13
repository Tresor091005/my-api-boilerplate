# Outils Clés du Projet

Ce document référence les outils majeurs intégrés à ce projet, chacun accompagné d'une brève description et d'un lien vers sa documentation détaillée.

## Application & API

-   **Laravel Reverb :** Un serveur WebSocket propriétaire pour des communications temps réel efficaces et sans dépendance tierce. [En savoir plus](../broadcasting/reverb.md)
-   **Laravel Sanctum :** Notre approche pour une authentification par token, sécurisée et extensible. [En savoir plus](../authentication/sanctum.md)
-   **Spatie Permissions :** Notre système de rôles et permissions, contextualisé par équipe et avec des permissions auto-découvertes. [En savoir plus](../iam/permissions.md)
-   **API Responses & Error Handling :** Description de notre approche standardisée pour les réponses d'API et la gestion des erreurs métier. [En savoir plus](../api/responses-and-errors.md)
-   **Dedoc Scramble :** Un générateur de documentation OpenAPI qui maintient votre documentation API à jour automatiquement. [En savoir plus](documentation/scramble.md)

## Architectural Blueprints

These documents showcase how core principles and tools are combined to build complete features. They serve as practical examples and blueprints for future development.

-   **Category CRUD:** A deep-dive into the implementation of a hierarchical CRUD module, demonstrating patterns for services, Data classes, assertions, and more. [En savoir plus](../features/catalog-categories.md)
-   **Unit Sync:** A high-performance synchronization feature for unit groups, demonstrating bulk operations (UPSERT), custom validation rules, and business assertions. [En savoir plus](../features/catalog-units.md)

## Infrastructure & Environnement

-   **Docker & FrankenPHP :** Une infrastructure multi-conteneurs optimisée pour le développement avec FrankenPHP (Caddy), Reverb, Horizon et un scheduler dédié. [En savoir plus](../infrastructure/docker.md)

## Qualité de Code & Automatisation

L'ensemble de ces outils compose le workflow de qualité du projet. [En savoir plus sur notre philosophie de la qualité](code-quality.md).

-   **Pint :** Un formateur de code PHP pour un style unifié.
-   **Rector :** Pour des refactorings et des mises à jour de code à grande échelle.
-   **PHPStan (Larastan) :** Détecte les erreurs et les incohérences de types avant l'exécution.
-   **IDE Helper :** Génère des fichiers d'aide pour l'autocomplétion dans l'IDE.
-   **Husky :** Un outil de gestion de hooks Git pour automatiser les vérifications avant les commits.

## Débogage

-   **Laravel Telescope :** Un outil puissant pour le débogage et l'inspection de votre application Laravel en environnement de développement. [En savoir plus](debugging/telescope.md)
