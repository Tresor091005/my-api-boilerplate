# Catalog Module

## Exceptions métier

Les invariants propres aux modèles sont regroupés par modèle : `CategoryException`, `OptionException`, `OptionValueException` et `ProductVariantException`. Utiliser leurs méthodes statiques nommées depuis les assertions.

Voir la [convention générale des exceptions métier](../../docs/architecture/coding-rules/exceptions.md).

## Tests

Cette section documente la strategie de test a suivre pour `catalog` (et reutilisable pour les autres modules).

### 1. Decoupage obligatoire

- `app-modules/catalog/tests/*`:
  - Tests **module-locaux** (metier)
  - Ciblent `Service`, `Data`, `Assertions`, persistance
  - **Sans** bootstrap IAM complet (`User`, `Role`, `Permission`, `Organization`)
- `tests/Feature/Integration/*`:
  - Tests **cross-module** (auth, policies, gates, middleware)
  - Couvre les matrices tenancy et permissions HTTP

Regle simple:
- "Que fait le metier ?" => module-local
- "Qui a le droit ?" => integration

### 2. Pattern module-local (catalog)

Dans les tests module-locaux:

- Initialiser le contexte tenant via le trait:
  - [InteractsWithCatalogTenantContext.php](./tests/Concerns/InteractsWithCatalogTenantContext.php)
- Appeler directement les services:
  - `CategoryService`, `OptionService`, `OptionValueService`, `ProductService`, `ProductVariantService`
- Valider les payloads via Form Request:
  - valider les payloads avec les règles de la Form Request et tester séparément le mapping `XxxData::fromArray(...)`
- Pour les collections/resources:
  - `->response()->getData(true)` puis assertions sur `data`

### 3. Pattern integration (catalog)

Les tests HTTP tenancy/authorization sont dans:

- [CatalogTenancyIntegrationTest.php](./../../tests/Feature/Integration/CatalogTenancyIntegrationTest.php)
- [CatalogAuthorizationIntegrationTest.php](./../../tests/Feature/Integration/CatalogAuthorizationIntegrationTest.php)

Ils couvrent:
- List: only my org
- Show/Update/Delete: allowed on my org, denied on other org
- Create: `organization_id` auto-assigne au tenant courant
- Permissions: `403` quand les permissions sont retirees

### 4. Factories et FK

Les factories `catalog` n'importent pas les modeles `organization`, mais respectent les FK:

- elles inserent une ligne minimale dans `organization_organizations` si necessaire
- elles reutilisent `currentOrganizationId()` quand disponible

Objectif: rester conforme a `ModularDependencyTest` tout en gardant des tests stables.

### 5. Commandes utiles

```bash
docker compose exec app php artisan test --compact app-modules/catalog/tests
docker compose exec app php artisan test --compact tests/Feature/Integration/CatalogTenancyIntegrationTest.php
docker compose exec app php artisan test --compact tests/Feature/Integration/CatalogAuthorizationIntegrationTest.php
docker compose exec app php artisan test --compact tests/Feature/Architecture/ModularDependencyTest.php
```

### 6. Checklist apres ajout de code

1. Ajouter/adapter test metier dans `app-modules/catalog/tests`.
2. Si impact auth/policy: ajouter test integration dans `tests/Feature/Integration`.
3. Verifier qu'aucun import cross-module interdit n'a ete introduit.
4. Executer `ModularDependencyTest` avant merge.
