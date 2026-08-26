<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Organization\Exceptions\OrganizationException;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Services\ExchangeRateService;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Currency::query()->firstOrCreate([
        'code' => 'USD',
    ], [
        'name'      => 'US Dollar',
        'symbol'    => '$',
        'precision' => 2,
    ]);
    Currency::query()->firstOrCreate([
        'code' => 'EUR',
    ], [
        'name'      => 'Euro',
        'symbol'    => '€',
        'precision' => 2,
    ]);

    $this->organization = Organization::factory()->create();
    $this->organization->settings()->create(['enable_currencies' => ['XOF', 'USD', 'EUR']]);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'exchange-rate-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'organization_exchange_rate.list',
        'organization_exchange_rate.retrieve',
        'organization_exchange_rate.create',
        'organization_exchange_rate.update',
        'organization_exchange_rate.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name'       => $permission,
            'guard_name' => 'sanctum',
        ]);
    }

    $this->memberRole->givePermissionTo($permissions);

    $token = $this->user->createToken('exchange-rate-token');
    $token->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $this->member->id,
            'member_role_id'  => $this->memberRole->id,
            'role_id'         => $this->role->id,
        ],
    ]);
    $this->withToken($token->plainTextToken);
});

it('manages future exchange rates through the API', function (): void {
    $effectiveAt = CarbonImmutable::now()->addDays(2)->startOfMinute();

    $response = $this->postJson('/v1/organization/exchange-rates?response=resource', [
        'source_currency_code' => 'usd',
        'target_currency_code' => 'eur',
        'rate'                 => '2.500000000000',
        'effective_at'         => $effectiveAt->toIso8601String(),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.source_currency_code', 'USD')
        ->assertJsonPath('data.target_currency_code', 'EUR')
        ->assertJsonPath('data.rate', '2.500000000000');

    $exchangeRateId = $response->json('data.id');

    $this->getJson('/v1/organization/exchange-rates?source_currency_code=usd')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $exchangeRateId);

    $this->getJson("/v1/organization/exchange-rates/{$exchangeRateId}")
        ->assertOk()
        ->assertJsonPath('data.id', $exchangeRateId);

    $this->patchJson("/v1/organization/exchange-rates/{$exchangeRateId}?response=resource", [
        'rate'         => '2.750000000000',
        'effective_at' => $effectiveAt->addDay()->toIso8601String(),
    ])
        ->assertOk()
        ->assertJsonPath('data.rate', '2.750000000000');

    $this->deleteJson("/v1/organization/exchange-rates/{$exchangeRateId}")
        ->assertNoContent();

    expect(ExchangeRate::query()->find($exchangeRateId))->toBeNull();
});

it('keeps effective rates immutable and converts minor units with the selected historical rate', function (): void {
    $rate = ExchangeRate::factory()->create([
        'organization_id'      => $this->organization->id,
        'source_currency_code' => 'USD',
        'target_currency_code' => 'XOF',
        'rate'                 => '2.500000000000',
        'effective_at'         => CarbonImmutable::now()->subDay(),
    ]);

    $this->patchJson("/v1/organization/exchange-rates/{$rate->id}?response=resource", [
        'rate'         => '2.750000000000',
        'effective_at' => CarbonImmutable::now()->addDay()->toIso8601String(),
    ])->assertUnprocessable();

    $this->deleteJson("/v1/organization/exchange-rates/{$rate->id}")
        ->assertUnprocessable();

    expect(app(ExchangeRateService::class)->resolveMinorConversion(
        '100',
        'USD',
        ExchangeRateContext::Default,
        CarbonImmutable::now(),
    ))->toMatchArray([
        'currency_code'                  => 'USD',
        'functional_currency_code'       => 'XOF',
        'amount_in_transaction_currency' => '100',
    ]);
});

it('does not expose another organizations exchange rates', function (): void {
    $otherOrganization = Organization::factory()->create();
    $rate = ExchangeRate::factory()->create([
        'organization_id' => $otherOrganization->id,
        'effective_at'    => CarbonImmutable::now()->addDay(),
    ]);

    $this->getJson("/v1/organization/exchange-rates/{$rate->id}")
        ->assertForbidden();
});

it('resolves the default exchange context and rejects disabled currencies', function (): void {
    ExchangeRate::factory()->create([
        'organization_id'      => $this->organization->id,
        'source_currency_code' => 'USD',
        'target_currency_code' => 'XOF',
        'context'              => 'default',
        'rate'                 => '2.500000000000',
        'effective_at'         => CarbonImmutable::now()->subDay(),
    ]);
    expect(app(ExchangeRateService::class)->resolveMinorConversion(
        '100',
        'USD',
        ExchangeRateContext::Default,
    ))->toMatchArray([
        'amount_in_functional_currency' => '3',
        'exchange_context'              => 'default',
    ]);

    $this->organization->settings()->update(['enable_currencies' => ['XOF']]);

    expect(fn () => app(ExchangeRateService::class)->resolveMinorConversion(
        '100',
        'USD',
    ))->toThrow(OrganizationException::class);
});

it('validates currency pairs and positive rates', function (): void {
    $this->postJson('/v1/organization/exchange-rates', [
        'source_currency_code' => 'USD',
        'target_currency_code' => 'USD',
        'context'              => 'sales',
        'rate'                 => '0',
        'effective_at'         => CarbonImmutable::now()->addDay()->toIso8601String(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'target_currency_code',
            'context',
            'rate',
        ]);
});
