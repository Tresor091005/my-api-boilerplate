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
-   **`redis_limiter` :** Instance **Redis 8** dédiée exclusivement au rate limiting avec une politique `noeviction` pour garantir la fiabilité des compteurs.
-   **`mailpit` :** Outil de capture d'emails pour le développement (Interface web sur le port 8025).

## 3. Optimisations de Développement
## 4. Commandes Utiles (via Makefile)

Pour garantir que toutes les commandes sont exécutées dans le contexte du conteneur (PHP 8.4, extensions, permissions), un `Makefile` est fourni à la racine du projet. **Il est fortement recommandé d'utiliser ces raccourcis.**

### Gestion des Conteneurs
-   **`make up`** : Démarre les conteneurs et entre dans le shell de l'application.
-   **`make down`** : Arrête les conteneurs.
-   **`make rs`** : Redémarre l'environnement.
-   **`make ps`** : Liste les conteneurs actifs.
-   **`make logs <service>`** : Affiche les logs d'un service (ex: `make logs app`).

### Commandes Application (Exécutées dans Docker)
-   **`make a <cmd>`** : Alias pour `php artisan` (ex: `make a migrate`).
-   **`make c <cmd>`** : Alias pour `composer` (ex: `make c install`).
-   **`make test`** : Lance la suite de tests Pest.
-   **`make pint`** : Lance le formateur Laravel Pint.
-   **`make phpstan`** : Lance l'analyse statique PHPStan (Larastan).
-   **`make rector`** : Lance les refactorings automatisés Rector.

## 5. Alias Internes (Inside Container)

Si vous êtes déjà à l'intérieur du conteneur (via `make up` ou `docker compose exec`), un alias `a` est configuré dans le `.bashrc` (`alias a='php artisan'`) pour accélérer vos commandes habituelles.

## 6. Infrastructure & Healthchecks

-   **Healthchecks** : Chaque service possède des tests de santé (`healthcheck`) pour garantir que les dépendances (DB, Redis) sont prêtes avant le démarrage de l'application.
-   **Permissions** : Le Dockerfile gère proprement les IDs d'utilisateur (1000:1000) pour éviter les problèmes de permissions sur les fichiers montés en volume.
