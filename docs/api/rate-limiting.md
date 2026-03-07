# API : Limite de Débit (Rate Limiting)

Ce document décrit la configuration et la stratégie de limitation de débit appliquée à l'API pour garantir la sécurité et la stabilité du système.

## 1. Vue d'ensemble

Nous utilisons les fonctionnalités natives de Laravel pour limiter le nombre de requêtes qu'un client (identifié par son adresse IP ou son ID utilisateur) peut effectuer dans un intervalle de temps donné.

Les limites sont définies dans `App\Providers\RateLimitServiceProvider`. 

**Note sur l'infrastructure :** Pour garantir que la limitation de débit ne soit pas affectée par la charge du cache applicatif ou des files d'attente, elle utilise une instance Redis dédiée (`redis_limiter`) via le store de cache `limiter`.

## 2. Limiteurs Configurés

### `api` (Global API)
Ce limiteur s'applique à la plupart des endpoints de l'API (`v1/*`).

-   **Limite :** 90 requêtes par minute.
-   **Identification :** Par ID utilisateur (si authentifié) ou par adresse IP.
-   **Middleware :** `throttle:api`.
-   **Application :** Inclus globalement dans le groupe de middleware `api` dans `bootstrap/app.php`, s'appliquant ainsi par défaut à toutes les routes de l'API.

### `auth` (Authentification)
Ce limiteur s'applique spécifiquement aux endpoints d'authentification sensibles pour prévenir les attaques par force brute.

-   **Limite :** 5 requêtes par minute.
-   **Identification :** Par adresse IP uniquement.
-   **Middleware :** `throttle:auth`.
-   **Endpoints concernés :**
    -   `POST /v1/auth/{type}/login`
    -   `POST /v1/auth/register`

Les autres routes du module d'authentification (comme `/me`, `/logout`) utilisent le limiteur `api` par défaut.

## 3. Réponses de Limitation

Lorsqu'un client dépasse sa limite, l'API renvoie une réponse standardisée :

-   **Code HTTP :** `429 Too Many Requests`.
-   **En-têtes :**
    -   `X-RateLimit-Limit`: Nombre total de requêtes autorisées.
    -   `X-RateLimit-Remaining`: Nombre de requêtes restantes.
    -   `Retry-After`: Nombre de secondes à attendre avant la prochaine requête.

## 4. Tests

La configuration du rate limiting est testée dans `tests/Feature/RateLimitTest.php`.
