# API : Réponses Standardisées et Gestion des Erreurs Métier

Ce document décrit l'approche standardisée pour les réponses JSON de l'API et la stratégie de gestion des erreurs métier via un système d'assertions.

## 1. Réponses d'API Standardisées (`ApiResponse`)

Pour garantir une communication cohérente et prévisible avec les clients de l'API, toutes les réponses JSON sont formatées à l'aide de la classe `Lahatre\Shared\Http\Responses\ApiResponse`.

### Structure
Cette classe fournit des méthodes statiques pour générer des `JsonResponse` avec une structure unifiée :
```json
{
  "status": 200,
  "message": "OK",
  "data": { ... }, // Présent en cas de succès avec des données
  "errors": { ... } // Présent en cas d'erreur
}
```

### Méthodes Principales
-   **Succès :**
    -   `ApiResponse::success($data, $message)`: Réponse 200 OK.
    -   `ApiResponse::created($data, $message)`: Réponse 201 Created.
    -   `ApiResponse::noContent()`: Réponse 204 No Content.
-   **Erreurs Client :**
    -   `ApiResponse::badRequest($message, $errors)`: 400 Bad Request.
    -   `ApiResponse::unauthenticated()`: 401 Unauthorized.
    -   `ApiResponse::unauthorized()`: 403 Forbidden.
    -   `ApiResponse::notFound()`: 404 Not Found.
    -   `ApiResponse::unprocessable($message, $errors)`: 422 Unprocessable Entity.

L'utilisation de cette classe garantit que les clients peuvent toujours s'attendre à la même structure de réponse, qu'il s'agisse d'un succès ou d'une erreur.

## 2. Gestion des Erreurs Métier avec les Assertions

La validation de base (types, champs requis) est gérée par les `Form Requests` de Laravel. Cependant, pour la logique métier plus complexe, nous utilisons un système d' "Assertions" encapsulé dans des objets dédiés.

### Le Problème
La logique métier (par exemple, "un utilisateur ne peut pas postuler à une offre s'il a déjà une candidature en cours", "un produit ne peut pas être ajouté au panier si son stock est nul") peut rapidement surcharger les services ou les contrôleurs, rendant le code difficile à lire et à tester.

### La Solution : Assertions et `AssertionException`

Nous créons des "objets d'assertion" qui encapsulent une logique métier spécifique et sont responsables de valider une condition. Si la condition n'est pas remplie, l'objet d'assertion lève une `AssertionException`.

`Lahatre\Shared\Exceptions\AssertionException` est une classe abstraite qui sert de base à toutes nos exceptions d'assertion métier.

**Exemple de création d'une `AssertionException` spécifique :**
```php
// @app-modules/catalog/src/Exceptions/ProductOutOfStockException.php
namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class ProductOutOfStockException extends AssertionException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            'Product is out of stock.',
            ['product_id' => $productId]
        );
    }
}
```

**Exemple de création d'un objet d'assertion :**
```php
// @app-modules/catalog/src/Assertions/ProductStockChecker.php
namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\ProductOutOfStockException;
use Lahatre\Catalog\Models\Product; // Supposons l'existence d'un modèle Product

final class ProductStockChecker
{
    /**
     * Asserts that the product has enough stock.
     *
     * @throws ProductOutOfStockException
     */
    public function assertStockIsAvailable(Product $product, int $requestedQuantity): void
    {
        if ($product->stock < $requestedQuantity) {
            throw new ProductOutOfStockException($product->id);
        }
    }
}
```

**Exemple d'utilisation dans un service :**
```php
// Dans un service de gestion de panier
use Lahatre\Catalog\Assertions\ProductStockChecker;

final class CartService
{
    public function __construct(private ProductStockChecker $productStockChecker) {}

    public function addProductToCart(string $productId, int $quantity): void
    {
        $product = $this->productRepository->find($productId);

        // L'assertion encapsule la logique et lève l'exception si nécessaire
        $this->productStockChecker->assertStockIsAvailable($product, $quantity);

        // ... logique d'ajout au panier
    }
}
```
Cette approche encapsule la logique de validation et l'erreur associée dans des classes claires et réutilisables, rendant le code de service plus concis et focalisé sur son objectif principal.

### Gestion centralisée des Erreurs

Dans `bootstrap/app.php`, toutes les exceptions qui héritent de `AssertionException` sont interceptées et formatées de manière standardisée :

```php
// @bootstrap/app.php
$exceptions->render(function (AssertionException $e, $request) {
    if ($request->expectsJson()) {
        return ApiResponse::unprocessable($e->getMessage(), [
            'type' => class_basename($e),
            'context' => app()->isProduction() ? null : $e->context(),
        ]);
    }
});
```

**Structure de la réponse d'erreur :**

-   **Code HTTP :** `422 Unprocessable Entity`.
-   **Corps JSON :**
    ```json
    {
      "status": 422,
      "message": "Product is out of stock.", // Message de l'exception
      "errors": {
        "type": "ProductOutOfStockException", // Nom de la classe de l'exception
        "context": { "product_id": "uuid-..." } // Données additionnelles (uniquement en dev/staging)
      }
    }
    ```

Cette stratégie permet de :
1.  Garder le code métier propre et focalisé sur le "happy path".
2.  Fournir des réponses d'erreur claires, structurées et utiles pour le débogage, sans exposer de détails sensibles en production.
3.  Traiter toutes les erreurs "métier" de la même manière, simplifiant la logique du client API.
