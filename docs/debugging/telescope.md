# Laravel Telescope

**Laravel Telescope** est un outil de débogage élégant qui fournit une vue d'ensemble perspicace des requêtes entrantes, des exceptions, des entrées de log, des requêtes de base de données, des tâches mises en file d'attente, des e-mails, des notifications, des caches, et bien plus encore, pour votre application Laravel. Il est conçu pour être utilisé principalement en **environnement de développement local**.

## Utilisation

Telescope est inclus dans le projet en tant que dépendance de développement (`require-dev` dans `composer.json`), ce qui signifie qu'il n'est pas déployé en production par défaut.

### Activation et Accès

L'activation de Telescope et son chemin d'accès sont gérés via votre fichier `.env` :

-   **`TELESCOPE_ENABLED=true`**: Active ou désactive Telescope. **Il est fortement recommandé de le définir sur `false` en production.**
-   **`TELESCOPE_PATH=debug`**: Définit le chemin d'accès à l'interface web de Telescope. Par défaut, il est configuré sur `/debug` dans notre `.env.example`.

Pour accéder à Telescope, assurez-vous que `TELESCOPE_ENABLED` est à `true` dans votre fichier `.env` local, puis naviguez vers l'URL de votre application suivie de `/debug` (ou du chemin que vous avez configuré). Par exemple: `http://localhost:8000/debug`.

### Fonctionnalités Clés

-   **Requêtes :** Inspectez les données de requête, les en-têtes, les sessions et les réponses.
-   **Exceptions :** Visualisez toutes les exceptions lancées par votre application.
-   **Logs :** Accédez facilement à toutes les entrées de log.
-   **Base de Données :** Examinez les requêtes SQL exécutées, y compris les temps d'exécution et les bindings.
-   **Jobs :** Surveillez l'exécution des tâches en file d'attente, y compris les données, les connexions et les temps d'exécution.
-   **Cache, Mail, Notifications :** Suivez toutes les interactions avec ces services.

## Important

Telescope collecte un grand nombre de données. Il est essentiel de ne l'activer que dans des environnements contrôlés (développement, staging) et de s'assurer qu'il est désactivé en production pour des raisons de performance et de sécurité.

Pour plus de détails sur la configuration avancée et les filtres, consultez la [documentation officielle de Laravel Telescope](https://laravel.com/docs/12.x/telescope).
