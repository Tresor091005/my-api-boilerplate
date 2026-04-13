---
name: test-generator
description: Standard de création de tests Pest 4. Définit l'anatomie d'un Feature Test (DTO, Assertions, Service, Resource) et les standards de découpage modulaire (module local vs intégration).
---

# Test Generator & Quality Manifesto

Ce skill définit les standards de qualité pour les tests Pest 4, avec priorité au découpage modulaire propre.

## 🚨 Règle d'Or : Couverture par l'Intention
Un test doit documenter une intention métier claire.  
Tout changement de comportement doit être précédé ou accompagné d'un test.

## 🧩 Découpage des Tests (Architecture Modulaire)

Pour respecter `ModularDependencyTest`, séparer les responsabilités de test:

1. **Tests module-locaux (`app-modules/<module>/tests`)**
   - Cible: Services, DTO, Assertions métier, Persistance.
   - Dépendances: uniquement celles autorisées par la matrice modulaire.
   - Éviter le bootstrap IAM complet (`User`, `Role`, `Permission`, `Organization`) si le module ne dépend pas de ces modules.

2. **Tests d’intégration cross-module (`tests/Feature/Integration/*`)**
   - Cible: authorization HTTP réelle (Policies/Gates), middleware auth, permissions.
   - Les dépendances IAM/Organization sont acceptées ici, car le scope est application-wide.

3. **Règle pratique**
   - Si le test répond à “qui a le droit ?” => intégration.
   - Si le test répond à “que fait le métier ?” => module local.
   - Ne pas mélanger les deux objectifs dans le même fichier.

4. **Contrainte FK sans dépendance de namespace**
   - Si un module référence une table externe via FK (ex: `organization_id`), créer les lignes minimales via `DB::table(...)` plutôt que d'importer les modèles du module externe.

## 🛡️ Isolation de l'Environnement (Stability)

Dans `beforeEach`:

1. **Contexte Team**
```php
setPermissionsTeamId($tenantId);
```

2. **Rate Limiter (si endpoints API testés)**
```php
RateLimiter::for('api', fn () => Limit::none());
```

## 🧬 Anatomie d'un Test Module-Local

### 1. Validation DTO
- Construire le DTO avec payload invalide.
- Attendre `ValidationException`.

### 2. Assertions Métier
- Simuler les états invalides.
- Vérifier l’exception métier attendue.

### 3. Logique Service & Persistance
- Appeler le service avec un payload valide.
- Vérifier DB (`assertDatabaseHas`) et relations.

### 4. Contrat de Sortie
- Vérifier `resource` ou `collection` (`->response()->getData(true)`).
- Vérifier les clés métier critiques.

## 🛠 Standards Pest & Structure

### Configuration type (service-first)
```php
<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(TestCase::class, RefreshDatabase::class);
```

### Organisation
- Préférer `it()` à `test()`.
- Nommer le test par comportement attendu.
- Garder un `beforeEach` minimal et explicite.

### Assertions fluides
```php
expect($unit->code)->toBe('KG')
    ->and($unit->name)->toBe('Kilogram');
```

## 📊 Datasets (Validation DTO)

```php
it('fails validation', function (array $data) {
    expect(fn () => new UnitSyncDTO($data))
        ->toThrow(ValidationException::class);
})->with([
    'missing code' => [['name' => 'Test']],
    'invalid type' => [['code' => 123]],
]);
```

## 🎭 Mocks & Fakes
- `Event::fake()`
- `Notification::fake()`
- `Storage::fake('public')`
- `Http::fake()` pour intégrations externes

## 🚀 Exécution Stable
```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array php artisan test --compact --filter NameOfTest
```
