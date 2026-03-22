# TODO: Isolation Totale des Modules

Cette liste regroupe les étapes nécessaires pour supprimer les dernières dépendances des modules vers le namespace `App` et atteindre une architecture 100% isolée.

## 1. Migration du Domaine "Identité & Accès" (IAM)
- [ ] Déplacer `App\Models\User\User` -> `Lahatre\Iam\Models\User`
- [ ] Déplacer `App\Models\User\UserProfile` -> `Lahatre\Iam\Models\UserProfile`
- [ ] Ajouter `iam` à la liste des hubs autorisés (`commonHubs`) dans `tests/Feature/Architecture/ModularDependencyTest.php`.

## 2. Création du Domaine "Carrière / Recrutement"
- [ ] Créer un module `career` (ou `hr`).
- [ ] Déplacer `App\Models\Career\Job` -> `Lahatre\Career\Models\Job`
- [ ] Déplacer `App\Models\Career\Application` -> `Lahatre\Career\Models\Application`

## 3. Migration du Domaine "Entreprise"
- [ ] Déplacer `App\Models\Company\Company` -> `Lahatre\Iam\Models\Company` (ou un nouveau module `organization`).
- [ ] Déplacer `App\Models\Company\CompanyMember` -> Idem.

## 4. Entités Transverses
- [ ] Déplacer `App\Models\Tag` -> `Lahatre\Shared\Models\Tag`.

## 5. Renforcement des Tests
- [ ] Une fois les migrations terminées, supprimer l'exception `App\Models` dans `tests/Feature/Architecture/ModularDependencyTest.php`.
- [ ] S'assurer que `php artisan test --compact tests/Feature/Architecture/ModularDependencyTest.php` passe sans exception.
