<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember();
    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->eco = $this->createTax([
        'code'             => 'ECO',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
});

it('simulates several lines on their own profiles with a grand total', function (): void {
    $vatOnEco = $this->createPercentageTax('18', [
        'code'         => 'VAT-ECO',
        'dependencies' => [['depends_on_tax_id' => $this->eco->id, 'operator' => 'add']],
    ]);
    $withEco = $this->createProfile([
        ['tax_id' => $vatOnEco->id, 'treatment' => 'standard'],
        ['tax_id' => $this->eco->id, 'treatment' => 'standard'],
    ]);
    $vatOnly = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $this->postJson('/v1/tax/simulations', ['lines' => [
        ['tax_profile_id' => $withEco->id, 'base_amount' => '10000', 'quantity' => '2'],
        ['tax_profile_id' => $vatOnly->id, 'base_amount' => '5000', 'quantity' => '1'],
    ]])
        ->assertOk()
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.schema_version', 1)
        ->assertJsonPath('data.lines.0.currency_code', 'XOF')
        ->assertJsonPath('data.lines.0.quantity', '2')
        ->assertJsonPath('data.lines.0.total_tax_amount', '2390')
        ->assertJsonPath('data.lines.0.taxes.1.code', 'VAT-ECO')
        ->assertJsonPath('data.lines.0.taxes.1.taxable_base_amount', '10500')
        ->assertJsonPath('data.lines.1.tax_profile_id', $vatOnly->id)
        ->assertJsonPath('data.lines.1.total_tax_amount', '900')
        ->assertJsonPath('data.total_tax_amount', '3290');
});

it('refuses an inactive profile unless inactive ones are included', function (): void {
    $draftTax = $this->createPercentageTax('10', ['code' => 'DRAFT-TAX', 'is_active' => false]);
    $profile = $this->createProfile([['tax_id' => $draftTax->id, 'treatment' => 'standard']], ['code' => 'DRAFT', 'is_active' => false]);
    $lines = [['tax_profile_id' => $profile->id, 'base_amount' => '10000', 'quantity' => '1']];

    $this->postJson('/v1/tax/simulations', ['lines' => $lines])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_inactive', ['code' => 'DRAFT']));

    $this->postJson('/v1/tax/simulations', ['lines' => $lines, 'include_inactive' => true])
        ->assertOk()
        ->assertJsonPath('data.lines.0.taxes.0.code', 'DRAFT-TAX')
        ->assertJsonPath('data.total_tax_amount', '1000');
});

it('lets a seller simulate without access to the tax configuration', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);
    $lines = [['tax_profile_id' => $profile->id, 'base_amount' => '10000', 'quantity' => '1']];
    $this->authenticateTaxMember(['tax_profile.simulate']);

    $this->postJson('/v1/tax/simulations', ['lines' => $lines])
        ->assertOk()
        ->assertJsonPath('data.total_tax_amount', '1800');
    $this->postJson('/v1/tax/simulations', ['lines' => $lines, 'include_inactive' => true])->assertForbidden();
    $this->getJson("/v1/tax/profiles/{$profile->id}")->assertForbidden();
});

it('refuses a simulation without the simulate permission', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);
    $this->authenticateTaxMember(array_values(array_diff($this->taxPermissions(), ['tax_profile.simulate'])));

    $this->postJson('/v1/tax/simulations', ['lines' => [['tax_profile_id' => $profile->id, 'base_amount' => '10000', 'quantity' => '1']]])
        ->assertForbidden();
});

it('refuses a profile of another organization in a line', function (): void {
    $foreignProfile = TaxProfile::factory()->create();

    $this->postJson('/v1/tax/simulations', ['lines' => [['tax_profile_id' => $foreignProfile->id, 'base_amount' => '100', 'quantity' => '1']]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_not_found'));
});

it('validates the lines to simulate', function (array $payload, string $errorPath): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $this->postJson('/v1/tax/simulations', ['lines' => [['tax_profile_id' => $profile->id, 'base_amount' => '10000', 'quantity' => '1', ...$payload]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorPath]);
})->with([
    'negative base amount'   => [['base_amount' => '-100'], 'lines.0.base_amount'],
    'fractional base amount' => [['base_amount' => '100.5'], 'lines.0.base_amount'],
    'zero quantity'          => [['quantity' => '0'], 'lines.0.quantity'],
    'missing profile'        => [['tax_profile_id' => null], 'lines.0.tax_profile_id'],
    'unknown sale unit'      => [['unit_code' => 'parsec'], 'lines.0.unit_code'],
]);

it('refuses an empty simulation', function (): void {
    $this->postJson('/v1/tax/simulations', ['lines' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);
});

it('simulates a per-unit tax on the sold quantity converted into the tax unit', function (): void {
    $perPack = $this->createTax([
        'code'             => 'PER-PACK',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'b-6',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '300']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perPack->id, 'treatment' => 'standard']]);

    $this->postJson('/v1/tax/simulations', ['lines' => [['tax_profile_id' => $profile->id, 'base_amount' => '1000', 'quantity' => '6', 'unit_code' => 'b']]])
        ->assertOk()
        ->assertJsonPath('data.lines.0.unit_code', 'b')
        ->assertJsonPath('data.lines.0.taxes.0.tax_quantity', '1')
        ->assertJsonPath('data.lines.0.taxes.0.tax_unit_code', 'b-6')
        ->assertJsonPath('data.lines.0.taxes.0.tax_amount', '300')
        ->assertJsonPath('data.total_tax_amount', '300');
});

it('refuses a per-unit simulation without a compatible sale unit', function (array $line, string $messageKey): void {
    $perPack = $this->createTax([
        'code'             => 'PER-PACK',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'b-6',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '300']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perPack->id, 'treatment' => 'standard']]);

    $this->postJson('/v1/tax/simulations', ['lines' => [['tax_profile_id' => $profile->id, 'base_amount' => '1000', 'quantity' => '6', ...$line]]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __($messageKey, ['code' => 'PER-PACK']))
        ->assertJsonPath('errors.context.tax_code', 'PER-PACK');
})->with([
    'sale unit of another unit group' => [['unit_code' => 'kg'], 'tax::exceptions.tax_unit_incompatible'],
    'sale unit missing'               => [[], 'tax::exceptions.sale_unit_required'],
]);
