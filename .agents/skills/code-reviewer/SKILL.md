---
name: code-reviewer
description: Auditeur de code pour Laravel Boost. Vérifie la conformité du code avec le Manifeste de Responsabilité et les Standards de Propreté (lisibilité, nommage, usage des collections).
---

# Skill: Code Reviewer

Ce skill a pour mission de vérifier qu'aucun fichier ne viole les principes définis dans le `code-generator/SKILL.md`.

## 🔍 Processus de Review

Quand on te demande de vérifier un fichier ou un dossier :

1. **Charger la Source de Vérité** : Lis systématiquement `.agents/skills/code-generator/SKILL.md` pour avoir les dernières règles à jour.
2. **Analyse de Responsabilité** :
    - Vérifie que le fichier est au bon endroit.
    - Vérifie qu'il ne fait pas plus que ce pour quoi il est prévu (ex: logique métier dans un Controller, validation dans un Service).
3. **Analyse de Style** :
    - **Nommage** : `camelCase` pour PHP, `snake_case` pour JSON/DB/Requests.
    - **Collections** : Pas de `array` là où une `Collection` Laravel devrait être.
    - **Signatures** : Types de retour et PHPDoc (`Collection<int, Model>`) obligatoires.
    - **Helpers** : Utilisation de `str()`, `data_get()`, `optional()`.
4. **Rapport de Non-Conformité** :
    - Liste chaque violation avec le fichier et la ligne concernée.
    - Explique **pourquoi** c'est une violation selon le manifeste.
    - Propose le code corrigé.

## 🚨 Points de Vigilance Critiques
- **N+1** : S'assurer que les Resources utilisent `whenLoaded()`.
- **Eager Loading** : S'assurer que le Service fait bien son job de chargement des relations.
- **Idempotence** : S'assurer que les Seeders utilisent `firstOrCreate()`.
- **Migrations** : Vérifier la présence des index sur les FK et les contraintes uniques.

## 🛑 Action Immédiate
Si une violation est détectée, n'attends pas la fin de ton tour pour le signaler. Bloque tout processus de commit ou de déploiement tant que le code n'est pas conforme.
