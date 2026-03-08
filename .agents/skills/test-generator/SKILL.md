---
name: test-generator
description: Standard de création de tests Pest 4. Définit l'anatomie d'un Feature Test (DTO, Assertions, Service, Réponse) et les standards de mocking et datasets pour les modules Laravel. Utiliser ce skill lors de la création de nouveaux tests ou de la modification de tests existants.
---

# Test Generator & Quality Manifesto

Ce skill définit l'anatomie et les standards de qualité pour les tests automatisés (Pest 4) au sein des modules.

## 🚨 Règle d'Or : Couverture par l'Intention
Un test ne doit pas seulement vérifier que le code "marche", il doit documenter une **intention métier**. Tout changement de comportement doit être précédé ou accompagné d'un test.

## 🛡️ Isolation de l'Environnement (Stability)

Pour garantir que les tests sont rapides et indépendants des services externes (Redis, Postgres), appliquez ces règles dans le `beforeEach` :

1.  **Désactivation du Rate Limiter** : Empêche les erreurs de connexion Redis (getaddrinfo).
    ```php
    RateLimiter::for('api', fn () => Limit::none());
    ```
2.  **Contexte IAM (Permissions & Team)** : Obligatoire si le module utilise des Policies ou `setPermissionsTeamId`.
    ```php
    setPermissionsTeamId(getDefaultTeamId());
    // Créer les permissions nécessaires si test de sécurité réel
    Permission::create(['name' => 'module.action', 'guard_name' => 'sanctum']);
    ```

## 🧬 Anatomie d'un Feature Test (Les 4 Piliers)

Chaque action majeure (souvent un endpoint API) doit être testée selon ces 4 axes :

### 1. Validation (DTO)
- **Objectif** : Vérifier que les données entrantes sont correctement filtrées et typées.
- **Action** : Envoyer des payloads invalides (manquants, mauvais format, hors limites).
- **Attente** : `422 Unprocessable Entity` avec les clés d'erreurs précises.
- **Outil** : Utiliser les **Datasets Pest** pour tester plusieurs cas de validation en une seule fonction.

### 2. Assertions & Exceptions Métier
- **Objectif** : Vérifier que les règles métier bloquent les actions illégitimes.
- **Action** : Simuler un état de base de données qui devrait faire échouer l'action (ex: doubler un code unique, supprimer une entité liée).
- **Attente** : L'exception attendue est levée (souvent `403 Forbidden` ou `422` selon le cas).

### 3. Logique Service & Persistance
- **Objectif** : Vérifier que le Service transforme correctement l'état du système.
- **Action** : Appeler l'action avec un payload valide.
- **Attente** : 
    - Vérifier la présence en base de données (`assertDatabaseHas`).
    - Vérifier les relations créées/mises à jour.
    - Vérifier les événements déclenchés (si applicable).

### 4. Format de Réponse (Resource)
- **Objectif** : Garantir la stabilité du contrat d'API.
- **Action** : Analyser le JSON de retour.
- **Attente** : `assertJsonStructure` ou `assertJsonPath` pour vérifier que les champs requis et les transformations (ex: dates, enums) sont corrects.

---

## 🛠 Standards Pest & Structure

### Configuration du Fichier
```php
<?php

declare(strict_types=1);

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use function Pest\Laravel\{postJson, getJson, patchJson, deleteJson, actingAs, assertDatabaseHas};

uses(TestCase::class, RefreshDatabase::class);
```

### Organisation (Describe/It)
- **`describe()`** : Grouper les tests par endpoint ou action (ex: `describe('POST /v1/catalog/units/sync', ...)`).
- **`it()`** : Nommer les tests par leur comportement attendu (ex: `it('validates required fields', ...)`).
- **Prière de ne pas utiliser `test()`**, préférer `it()` pour la lisibilité ("it validates...").
- **`beforeEach()`** : Initialiser l'utilisateur (`actingAs`), le Team ID et désactiver les Rate Limiters.

### Assertions Fluides
Préférer les attentes fluides de Pest :
```php
expect($unit->code)->toBe('KG')
    ->and($unit->name)->toBe('Kilogram');
```

---

## 📊 Utilisation des Datasets (Validation)

Pour tester les règles de validation d'un DTO sans dupliquer le code :
```php
it('fails validation', function (array $data, string $errorField) {
    postJson(route('lahatre.catalog.units.sync'), $data)
        ->assertJsonValidationErrors($errorField);
})->with([
    'missing code' => [['name' => 'Test'], 'code'],
    'invalid type' => [['code' => 123], 'code'],
]);
```

---

## 🎭 Mocks & Fakes
- **Events** : `Event::fake()` au début du test pour vérifier `Event::assertDispatched`.
- **Notifications** : `Notification::fake()`.
- **Storage** : `Storage::fake('public')`.
- **Integrations** : Mocking des services externes (API tiers) via `Http::fake()` ou mock d'interface si nécessaire.

## 🚀 Exécution des Tests (Fiabilité Maximale)
Si l'environnement local n'est pas stable (DB/Redis), forcer SQLite et Array :
```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array php artisan test --compact --filter NameOfTest
```
