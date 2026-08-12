# Exceptions métier

Les exceptions métier héritent de `Lahatre\\Shared\\Exceptions\\AssertionException`. Elles sont réservées aux invariants qui ne peuvent pas être exprimés correctement par une `FormRequest`, une règle de validation ou une contrainte de base de données.

## Une classe par modèle

Quand plusieurs règles concernent le même modèle, elles sont regroupées dans une seule exception nommée `<Model>Exception`. Chaque règle est exposée par une méthode statique descriptive :

```php
throw CategoryException::hasChildren($category);
throw CategoryException::cannotBeDescendantParent($category, $parentId);
```

Cette organisation fournit un catalogue lisible des invariants du modèle et évite de multiplier les fichiers pour des variantes d’un même domaine.

Les méthodes statiques doivent construire le message traduit avec `__()` et conserver les identifiants utiles dans `context()`.

## Quand garder une exception séparée

Une exception reste dans son propre fichier lorsqu’elle décrit un workflow ou une infrastructure plutôt qu’un modèle unique. Exemples : organisation courante, reversal de transaction, idempotence, stock insuffisant et échec d’authentification.

Les exceptions transversales ne doivent pas être artificiellement rattachées à un modèle si la règle concerne plusieurs objets ou une étape de processus.

## Catalogue actuel

- `catalog` : `CategoryException`, `OptionException`, `OptionValueException`, `ProductVariantException`.
- `master` : `UnitException`, `TagException`.
- `pricing` : les erreurs de résolution et de validation restent regroupées par responsabilité car elles concernent plusieurs contrats ou cibles polymorphes.
- `inventory` : les erreurs de transaction, de stock, d’organisation et d’idempotence restent séparées car elles concernent des workflows ou plusieurs modèles.
- `iam` : les erreurs d’authentification restent séparées du modèle utilisateur.

Les tests peuvent inspecter le message ou le contexte pour distinguer la règle précise au sein d’une exception par modèle.
