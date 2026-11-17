<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Organization\Models\Organization;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(['sale_setting.retrieve', 'sale_setting.update']);
});

function saleSettingModes(): array
{
    return [
        ['code' => 'counter', 'invoicing' => 'invoice_on_confirmation', 'delivery' => 'immediate'],
        ['code' => 'bill_and_hold', 'invoicing' => 'invoice_on_confirmation', 'delivery' => 'deferred'],
        ['code' => 'progress_billing', 'invoicing' => 'invoice_on_fulfillment', 'delivery' => 'deferred'],
    ];
}

it('reads the counter mode and the three modes while the organization has no settings', function (): void {
    $this->getJson('/v1/sale/settings')
        ->assertOk()
        ->assertExactJson(['data' => ['default_mode' => 'counter', 'modes' => saleSettingModes()]]);

    expect(SaleSetting::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse();
});

it('creates the settings on the first write, then changes them', function (): void {
    $this->putJson('/v1/sale/settings', ['default_mode' => 'bill_and_hold'])
        ->assertOk()
        ->assertExactJson(['data' => ['default_mode' => 'bill_and_hold', 'modes' => saleSettingModes()]]);
    $this->putJson('/v1/sale/settings', ['default_mode' => 'progress_billing'])
        ->assertOk()
        ->assertJsonPath('data.default_mode', 'progress_billing');

    $this->getJson('/v1/sale/settings')->assertOk()->assertJsonPath('data.default_mode', 'progress_billing');

    $settings = SaleSetting::query()->where('organization_id', $this->organization->id)->sole();
    expect($settings->default_mode)->toBe(SaleMode::ProgressBilling);
});

it('requires a known mode', function (array $payload): void {
    $this->putJson('/v1/sale/settings', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('default_mode');
})->with([
    'missing' => [[]],
    'empty'   => [['default_mode' => null]],
    'unknown' => [['default_mode' => 'layaway']],
]);

it('requires the permission of each call', function (string $method, string $permission): void {
    $this->authenticateTaxMember(array_values(array_diff(['sale_setting.retrieve', 'sale_setting.update'], [$permission])));

    $this->json($method, '/v1/sale/settings', ['default_mode' => 'counter'])->assertForbidden();
    expect(SaleSetting::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse();
})->with([
    'read'  => ['GET', 'sale_setting.retrieve'],
    'write' => ['PUT', 'sale_setting.update'],
]);

it('keeps the settings of each organization apart', function (): void {
    $foreignOrganization = Organization::factory()->create();
    SaleSetting::factory()->create(['organization_id' => $foreignOrganization->id, 'default_mode' => 'progress_billing']);

    $this->getJson('/v1/sale/settings')->assertOk()->assertJsonPath('data.default_mode', 'counter');
    $this->putJson('/v1/sale/settings', ['default_mode' => 'bill_and_hold'])->assertOk();

    expect(SaleSetting::query()->where('organization_id', $foreignOrganization->id)->value('default_mode'))
        ->toBe(SaleMode::ProgressBilling)
        ->and(SaleSetting::query()->where('organization_id', $this->organization->id)->value('default_mode'))
        ->toBe(SaleMode::BillAndHold);
});
