# Soft-Delete Rollout Prompt (Modules Restants)

## Objectif
Appliquer `SoftDeletes` progressivement sur les autres modules, en conservant les garanties multi-tenant (`organization_id`) et en gardant la suite de tests verte.

## Règles de mise en oeuvre
1. Ajouter `SoftDeletes` uniquement aux modèles ciblés pour le lot en cours.
2. Créer une migration dédiée par table pour:
   - ajouter `deleted_at`,
   - ajouter un index global sur `deleted_at`,
   - convertir les index métier/filtres en index partiels actifs (`WHERE deleted_at IS NULL`) si pertinent,
   - adapter les contraintes uniques (souvent via index unique partiels actifs).
3. Mettre à jour les DTO/règles `unique`/`exists` pour exclure les enregistrements soft-deleted (`whereNull('deleted_at')`) quand nécessaire.
4. Adapter les services:
   - conserver les garde-fous tenant (`organization_id`) côté service,
   - retirer les checks parent/enfant redondants si `scopeBindings()` couvre déjà l’appartenance route.
5. Adapter assertions/policies seulement si nécessaire au nouveau comportement soft-delete.
6. Conserver les suppressions physiques des pivots quand demandé explicitement (éviter la croissance DB inutile).
7. Mettre à jour `config/model-integrity.php`:
   - exemptions minimales pour index globaux assumés,
   - éviter d’ajouter `deleted_at` partout à la main (la règle d’architecture l’ignore automatiquement pour l’index mono-colonne `deleted_at`).

## Contrat Qualité (obligatoire)
1. Ajouter/adapter les tests feature du service concerné:
   - après `delete()`: `query()->exists()` false,
   - `withTrashed()->exists()` true,
   - `deleted_at` non null.
2. Ajouter/adapter tests d’intégration HTTP:
   - après `DELETE`: `GET` doit retourner `404`,
   - l’entité doit rester présente en `withTrashed()`.
3. Exécuter au minimum:
   - tests ciblés du module,
   - `tests/Feature/Integration/CatalogTenancyIntegrationTest.php` (ou équivalent module),
   - `php artisan test --compact` complet.
4. Aucun merge tant que la suite complète n’est pas verte.

## Notes PostgreSQL importantes
1. `Eloquent::upsert()` ne supporte pas les cibles `ON CONFLICT ... WHERE deleted_at IS NULL`.
2. Si un bulk upsert est nécessaire avec unique partielle, utiliser SQL PostgreSQL natif (`DB::statement`) avec clause `ON CONFLICT (...) WHERE deleted_at IS NULL`.
3. Si une unique existante est une contrainte, utiliser `ALTER TABLE ... DROP CONSTRAINT` (et pas `DROP INDEX`) avant recréation.

## Process recommandé par lot
1. Choisir 1 ou 2 aggregates max.
2. Implémenter modèle + migration + DTO + service.
3. Ajuster tests feature/intégration.
4. Passer la suite complète.
5. Commit.

## Définition de terminé
- Comportement soft-delete correct en service + HTTP.
- Multi-tenancy préservée.
- Intégrité DB conforme (`model-integrity` + architecture tests).
- Suite de tests complète verte.
