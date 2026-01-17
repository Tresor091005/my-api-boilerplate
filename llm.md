# Utilisation des LLMs dans ce projet

Ce document a pour but de définir les bonnes pratiques concernant l'utilisation des modèles de langage (LLMs) dans le cadre de ce projet. L'objectif est de maximiser leur efficacité tout en garantissant la qualité, la cohérence et la maintenabilité du code.

## Principes Généraux

1.  **Le développeur reste le pilote :** Les LLMs sont des assistants. Le code qu'ils génèrent doit être considéré comme une suggestion, et non comme une solution finale. Il est de la responsabilité du développeur de comprendre, valider, et tester chaque ligne de code.

2.  **Qualité et Conventions :** Le code produit doit impérativement respecter les conventions de nommage, de style et d'architecture du projet. Ne faites pas aveuglément confiance au LLM pour respecter le contexte.

3.  **Sécurité :** Ne jamais inclure d'informations sensibles (clés d'API, mots de passe, données personnelles) dans les prompts envoyés aux LLMs.

4.  **Itération et Précision :** Soyez précis dans vos demandes. Un bon prompt est la clé pour obtenir un résultat pertinent. N'hésitez pas à itérer et à affiner votre demande.

## Cas d'usage recommandés

*   **Boilerplate et Répétition :** Génération de code répétitif (création de tests unitaires, de DTOs, de routes, etc.).
*   **Explication de code :** Comprendre rapidement une portion de code complexe ou du code hérité.
*   **Refactoring :** Suggérer des améliorations ou des simplifications de code.
*   **Documentation :** Générer une documentation de base pour des fonctions ou des classes.
*   **Brainstorming :** Explorer différentes approches pour résoudre un problème.

## Travail en équipe

Pour assurer une collaboration efficace :

*   **Partage de prompts :** Si vous développez un prompt particulièrement efficace pour une tâche récurrente, partagez-le avec l'équipe.
*   **Revue de code :** La revue de code est encore plus cruciale lorsque des LLMs sont utilisés. Portez une attention particulière à la logique métier et aux éventuels effets de bord.

Ce document est vivant et a vocation à être enrichi par l'expérience de chacun.
