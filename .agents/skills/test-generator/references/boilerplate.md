# Pest Feature Test Boilerplate (Stabilized)

Copiez-collez cette structure pour démarrer un nouveau test robuste dans un module.

```php
<?php

declare(strict_types=1);

namespace Lahatre\MyModule\Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Lahatre\Iam\Models\Permission;
use Lahatre\MyModule\Models\MyModel;
use Tests\TestCase;
use function Pest\Laravel\{postJson, getJson, patchJson, deleteJson, actingAs, assertDatabaseHas};

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 1. Désactiver les limites de débit pour les tests
    RateLimiter::for('api', fn () => Limit::none());

    // 2. Initialiser le contexte IAM (Team ID)
    setPermissionsTeamId(getDefaultTeamId());

    // 3. Créer les permissions nécessaires
    Permission::firstOrCreate(['name' => 'mymodule.action', 'guard_name' => 'sanctum']);

    // 4. Agir en tant qu'utilisateur authentifié
    $this->user = User::factory()->create();
    actingAs($this->user);
});

describe('POST /v1/my-module/action', function () {

    it('validates the request', function (array $data, string $errorField) {
        $this->user->givePermissionTo('mymodule.action');

        postJson(route('lahatre.my-module.action'), $data)
            ->assertJsonValidationErrors($errorField);
    })->with([
        'missing field' => [['other' => 'val'], 'field'],
        'invalid type'  => [['field' => 123], 'field'],
    ]);

    it('executes the action and persists data', function () {
        $this->user->givePermissionTo('mymodule.action');

        $data = [
            'name' => 'Test Item',
            'code' => 'TEST-001',
        ];

        postJson(route('lahatre.my-module.action'), $data)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Item');

        assertDatabaseHas('my_module_table', [
            'name' => 'Test Item',
        ]);
    });

    it('fails if business rule is violated', function () {
        $this->user->givePermissionTo('mymodule.action');
        
        // Setup state that violates a rule
        $model = MyModel::factory()->create(['is_active' => true]);

        // Act
        postJson(route('lahatre.my-module.action'), ['id' => $model->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type', 'MyBusinessRuleException');
    });
});
```

### Commande d'exécution recommandée
```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array php artisan test --compact --filter MyTestName
```
