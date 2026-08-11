# Décision : séparation Form Request et Data

## Problème résolu

`LahatreDTO` validait, normalisait, castait et construisait les objets de transport dans une même abstraction. Les DTO imbriqués relançaient en plus leur propre Validator, ce qui faisait perdre les chemins d'erreur du parent tels que `units.0.name` ou `variants.1.options.0.value`.

## Décision appliquée

- Les Form Requests constituent la frontière de validation HTTP et valident les tableaux imbriqués avec les règles Laravel `field.*`.
- Les classes Data sont des transports immuables construits par `::fromArray()` après validation.
- Les Controllers gardent les Gates et Policies.
- Les Services consomment des Data et réalisent un mapping explicite vers les modèles.
- `MissingValue` distingue une clé absente d'une valeur explicite `null`, `false`, `0`, chaîne vide ou tableau vide.

## Statut de migration

Les modules IAM, Catalog, Master et Inventory ont été migrés. La classe `LahatreDTO`, ses casts, ses concerns, sa commande Artisan, ses stubs et ses tests dédiés ont été supprimés.

La convention complète est décrite dans [Form Requests et classes Data](docs/data/form-requests-and-data.md) et dans [les règles centrales](.agents/CODEBASE_RULES.md).
