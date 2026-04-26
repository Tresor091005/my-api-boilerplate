   ## Refonte Des Skills
  - Faire l’inventaire complet des skills actuels.
  - Identifier les skills réellement utilisés de façon répétée.
  - Supprimer les skills redondants, trop verbeux, ou trop généraux.
  - Repartir d’un noyau minimal de skills vraiment utiles.
  - Écrire un `agent.md` global avant de réécrire les skills.
  - Déplacer les règles transverses du repo dans `agent.md`.
  - Réserver les skills aux workflows spécialisés.
  - Ajouter un exemple concret d’usage dans chaque skill.
  - Ajouter une section “quand ne pas l’utiliser” dans chaque skill.
  - Valider chaque nouveau skill sur un cas réel avant de le garder.

  ## Noyau Minimal De Skills À Recréer
  - `project-mapper`
    But : cartographier le repo, les modules, les frontières, les dépendances.
  - `module-generator`
    But : créer/étendre un module Laravel modulaire proprement.
  - `crud-surface`
    But : produire DTO + Service + Controller + Resource + routes.
  - `query-read-models`
    But : lectures, agrégats, summaries, pagination, resources spécialisées.
  - `test-generator`
    But : générer des tests Pest Feature cohérents.
  - `code-reviewer`
    But : review centrée bugs, régressions, N+1, contrats API.
  - `localization-manager`
    But : gérer les traductions et la cohérence des clés.
  - `docs-writer`
    But : produire docs d’API, exemples JSON, notes de module.

  ## Version Encore Plus Minimaliste
  - `project-mapper`
  - `crud-surface`
  - `query-read-models`
  - `test-generator`
  - `code-reviewer`

  ## Structure Standard D’Un Skill
  - Définir `Quand l’utiliser`
  - Définir `Quand ne pas l’utiliser`
  - Définir `Inputs attendus`
  - Définir `Décisions obligatoires`
  - Définir `Checklist d’implémentation`
  - Définir `Checklist de validation`
  - Définir `Exemple minimal`
  - Définir `Anti-patterns`

  ## `agent.md`
  - Écrire un `agent.md` global, court, stable, et transversal.
  - Y mettre l’architecture du repo.
  - Y mettre les conventions de modules.
  - Y mettre les règles de responsabilité par couche.
  - Y mettre la politique de tests.
  - Y mettre la politique de review.
  - Y mettre les règles de nommage.
  - Y mettre les règles de non-régression.
  - Y mettre les règles d’édition sûres.
  - Y mettre les attentes de sortie finale.

  ## Ce Que `agent.md` Ne Doit Pas Contenir
  - Ne pas y mettre de longues recettes spécialisées.
  - Ne pas dupliquer le contenu des skills.
  - Ne pas y mettre des checklists propres à une seule tâche.
  - Ne pas y mettre des conventions métier trop locales ou trop temporaires.

  ## Coexistence Avec Laravel Boost
  - Utiliser Laravel Boost pour les bonnes pratiques Laravel génériques.
  - Utiliser `agent.md` pour les règles globales du repo.
  - Utiliser les skills pour les workflows spécialisés du projet.
  - Éviter toute duplication entre Boost, `agent.md`, et les skills.
  - Garder la répartition suivante :
    - Boost : “comment bien faire du Laravel”
    - `agent.md` : “comment bien travailler dans ce repo”
    - skills : “comment exécuter telle mission spécialisée dans ce repo”

  ## Plan De Réécriture Des Skills
  - Geler les skills actuels dans un dossier d’archive.
  - Écrire `agent.md` en premier.
  - Réécrire `project-mapper`.
  - Réécrire `test-generator`.
  - Réécrire `crud-surface`.
  - Ajouter `query-read-models`.
  - Ne réintroduire les autres skills qu’en cas de besoin réel.

  ## Inventory / Suite Du Projet
  - Comme l’essentiel semble maintenant en place sur inventory read/write, passer au multi-tenancy.
  - Ajouter l’activity log.
  - Ajouter/compléter le soft delete là où nécessaire.
  - Inspecter les index manquants.
  - Vérifier les index utiles pour les lectures agrégées inventory.
  - Vérifier les index utiles pour les requêtes catalog avec résumé inventory.
  - Vérifier les index utiles pour multi-tenancy.
  - Vérifier les index utiles pour activity log.
  - Revoir les contraintes et uniques conditionnels si nécessaire.