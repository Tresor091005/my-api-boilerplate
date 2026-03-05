# Infrastructure Docker & Environnement de Développement

Ce projet utilise une architecture Docker robuste basée sur les images de **serversideup**, optimisée pour Laravel avec **FrankenPHP**.

## 1. FrankenPHP en Mode Normal

L'application est propulsée par `serversideup/php:8.4-frankenphp`. Nous utilisons FrankenPHP en **mode normal** (via Caddy) plutôt qu'en mode worker pur par défaut.

-   **Avantages de Caddy :** En mode normal, nous profitons de toute la puissance de Caddy pour la gestion du serveur web, la compression, et la facilité de configuration.
-   **Flexibilité Octane :** Cette configuration est conçue pour être "Octane-ready". Si les besoins de performance l'exigent, le passage à Laravel Octane se fait très facilement en changeant simplement la commande de démarrage ou la configuration FrankenPHP.

## 2. Architecture Multi-Conteneurs

Pour garantir un environnement de développement fidèle à la production et une séparation claire des responsabilités, l'infrastructure est découpée en plusieurs services spécialisés dans le `docker-compose.yml` :

### Services Applicatifs (Partageant la même image)
Tous ces services utilisent le même `Dockerfile` pour garantir la cohérence des dépendances et du code :
-   **`app` :** Le serveur web principal (FrankenPHP/Caddy) sur le port 8000.
-   **`reverb` :** Le serveur WebSocket (Laravel Reverb) tournant sur le port 6001.
-   **`horizon` :** La gestion des files d'attente (Queues) via Laravel Horizon.
-   **`scheduler` :** Le gestionnaire de tâches planifiées (`artisan schedule:work`).

### Services d'Infrastructure
-   **`db` :** Base de données **PostgreSQL 18** (Alpine).
-   **`redis` :** Serveur **Redis 8** (Alpine) pour le cache, les queues et Reverb.
-   **`mailpit` :** Outil de capture d'emails pour le développement (Interface web sur le port 8025).

## 3. Optimisations de Développement

-   **Alias Artisan :** Un alias `a` est configuré dans le `.bashrc` du conteneur (`alias a='php artisan'`) pour accélérer les commandes en ligne de commande.
-   **Healthchecks :** Chaque service possède des tests de santé (`healthcheck`) pour garantir que les dépendances (DB, Redis) sont prêtes avant le démarrage de l'application.
-   **Permissions :** Le Dockerfile gère proprement les IDs d'utilisateur (1000:1000) pour éviter les problèmes de permissions sur les fichiers montés en volume.

## 4. Commandes Utiles

-   Démarrer l'environnement : `docker-compose up -d`
-   Voir les logs : `docker-compose logs -f`
-   Accéder au conteneur app : `docker-compose exec -u www-data app bash`
