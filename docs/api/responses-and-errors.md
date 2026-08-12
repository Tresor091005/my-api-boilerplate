# API : Réponses Standardisées et Gestion des Erreurs Métier

Ce document décrit l'approche standardisée pour les réponses JSON de l'API et la stratégie de gestion des erreurs métier via un système d'assertions.

## 1. Structure des Réponses JSON

Toutes les réponses de l'API suivent une structure cohérente pour faciliter l'intégration côté client.

### Structure de Base
Chaque réponse JSON contient au minimum un champ `message` :
```json
{
  "message": "Description de l'état de la requête"
}
```

### Succès avec Données
Pour les requêtes retournant des ressources, les données sont encapsulées dans un champ `data` :
```json
{
  "message": "OK",
  "data": {
    "id": "...",
    "name": "..."
  }
}
```

### Listes et Pagination
Pour les routes d'index paginées, un champ `meta` est ajouté pour fournir les informations de pagination :
```json
{
  "message": "OK",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

### Erreurs
En cas d'erreur (validation, erreur métier, etc.), un champ `errors` contient les détails :
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## 2. Gestion des Erreurs Métier avec les Assertions

Les règles d’organisation des exceptions sont documentées dans [Exceptions métier](../coding-rules/exceptions.md). Les règles d’un même modèle sont regroupées dans une exception par modèle et exposées par des méthodes statiques nommées.

La validation de base (types, champs requis) est gérée par les `DTOs` ou les `Form Requests`. Cependant, pour la logique métier plus complexe, nous utilisons un système d' "Assertions" encapsulé dans des objets dédiés.

### Le Problème
La logique métier (par exemple, "un utilisateur ne peut pas postuler à une offre s'il a déjà une candidature en cours") peut rapidement surcharger les services ou les contrôleurs.

### La Solution : Assertions et `AssertionException`

Nous créons des "objets d'assertion" qui encapsulent une logique métier spécifique. Si la condition n'est pas remplie, l'objet d'assertion lève une `AssertionException`.

`Lahatre\Shared\Exceptions\AssertionException` est une classe abstraite qui sert de base à toutes nos exceptions d'assertion métier.

**Exemple de création d'une `AssertionException` spécifique :**
```php
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

### Gestion centralisée des Erreurs

Dans `bootstrap/app.php`, toutes les exceptions qui héritent de `AssertionException` sont interceptées et formatées de manière standardisée :

```php
$exceptions->render(function (AssertionException $e, $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors'  => [
                'type'    => class_basename($e),
                'context' => app()->isProduction() ? null : $e->context(),
            ],
        ], 422);
    }
});
```
