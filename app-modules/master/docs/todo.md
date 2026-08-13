# TODO - Universal Tags

## API de gestion des tags

- [ ] Ajouter des routes API dédiées pour gérer le référentiel de tags, sans passer par `attach` sur un modèle.
- [ ] Ajouter un endpoint de création de tag.
- [ ] Ajouter un endpoint de mise à jour de tag pour gérer le renommage.
- [ ] Ajouter un endpoint de fusion de tags.
- [ ] Ajouter un endpoint de suppression de tag.
- [ ] Interdire la suppression d'un tag encore utilisé par au moins un `taggable`.
- [ ] Confirmer la règle métier: `detach` dissocie seulement le tag du modèle et ne supprime jamais le tag lui-même.
- [ ] Ajouter un endpoint pour ordonner les tags d'un type via `order_col`.

## Utilitaires côté modèle

- [ ] Ajouter des utilitaires `hasTagOfType`, `hasAnyTagsOfType` et `hasAllTagsOfType`.
- [ ] Ajouter un utilitaire de chargement ciblé des tags d'un type.

## Règles métier à conserver

- [ ] Conserver le type comme axe central des opérations de lecture et d'écriture.
- [ ] Ne pas autoriser la suppression d'un tag utilisé.
- [ ] Ne pas coupler `detach` avec une suppression automatique du tag.
