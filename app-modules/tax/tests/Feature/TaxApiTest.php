<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember();
});

/** @return array<string, mixed> */
function vatPayload(array $overrides = []): array
{
    return [
        'code'             => 'vat',
        'name'             => '  Value   added tax ',
        'kind'             => 'vat',
        'calculation_type' => 'percentage',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '18']],
        ...$overrides,
    ];
}

it('manages a tax through the API', function (): void {
    $response = $this->postJson('/v1/tax/taxes?response=resource&include=brackets', vatPayload())
        ->assertCreated()
        ->assertJsonPath('data.code', 'VAT')
        ->assertJsonPath('data.name', 'Value added tax')
        ->assertJsonPath('data.kind', 'vat')
        ->assertJsonPath('data.base_type', 'amount_with_dependencies')
        ->assertJsonPath('data.rounding_mode', 'half_up')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.brackets.0.value', '18.000000');
    $taxId = $response->json('data.id');

    $this->getJson('/v1/tax/taxes?kind=vat')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $taxId);

    $this->putJson("/v1/tax/taxes/{$taxId}?response=resource&include=brackets", vatPayload([
        'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '19.5']],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.brackets')
        ->assertJsonPath('data.brackets.0.value', '19.500000');

    $this->deleteJson("/v1/tax/taxes/{$taxId}")->assertNoContent();

    expect(Tax::query()->find($taxId))->toBeNull()
        ->and(Tax::withTrashed()->find($taxId)?->deleted_at)->not->toBeNull();
});

it('stores a progressive tax with its dependencies and per-unit settings', function (): void {
    $eco = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);

    $this->postJson('/v1/tax/taxes?response=resource&include=brackets,dependencies', vatPayload([
        'is_progressive' => true,
        'bracket_mode'   => 'marginal',
        'rounding_mode'  => 'down',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => null, 'value' => '18', 'tax_amount_max' => '50000'],
        ],
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.brackets')
        ->assertJsonPath('data.brackets.1.tax_amount_max', '50000.000000')
        ->assertJsonPath('data.dependencies.0.depends_on_tax_id', $eco->id)
        ->assertJsonPath('data.dependencies.0.operator', 'add');

    $this->getJson("/v1/tax/taxes/{$eco->id}")
        ->assertOk()
        ->assertJsonPath('data.fixed_mode', 'per_tax_unit')
        ->assertJsonPath('data.tax_unit_code', 'kg');
});

it('validates the shape of a tax definition', function (array $payload, string $errorPath): void {
    $this->postJson('/v1/tax/taxes', vatPayload($payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorPath]);
})->with([
    'code with spaces'             => [['code' => 'v a t'], 'code'],
    'unknown kind'                 => [['kind' => 'sales'], 'kind'],
    'decimal with too many places' => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '18.1234567']]], 'brackets.0.value'],
    'numeric bracket value'        => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => 18]]], 'brackets.0.value'],
    'no bracket'                   => [['brackets' => []], 'brackets'],
    'unknown tax unit'             => [['tax_unit_code' => 'parsec'], 'tax_unit_code'],
    'unknown operator'             => [['dependencies' => [['depends_on_tax_id' => '0199f7a0-0000-7000-8000-000000000000', 'operator' => 'multiply']]], 'dependencies.0.operator'],
]);

it('refuses incoherent tax definitions', function (array $payload, string $messageKey): void {
    $this->postJson('/v1/tax/taxes', vatPayload($payload))
        ->assertUnprocessable()
        ->assertJsonPath('message', __($messageKey, ['precision' => 0]));
})->with([
    'fixed without mode'                  => [['calculation_type' => 'fixed'], 'tax::exceptions.fixed_mode_required'],
    'percentage with fixed mode'          => [['fixed_mode' => 'per_line'], 'tax::exceptions.fixed_mode_not_allowed'],
    'per unit without unit'               => [['calculation_type' => 'fixed', 'fixed_mode' => 'per_tax_unit'], 'tax::exceptions.tax_unit_required'],
    'progressive percentage without mode' => [['is_progressive' => true, 'brackets' => [
        ['base_min' => '0', 'base_max' => '10', 'value' => '0'],
        ['base_min' => '10', 'base_max' => null, 'value' => '5'],
    ]], 'tax::exceptions.bracket_mode_required'],
    'fixed tax with bracket mode' => [['calculation_type' => 'fixed', 'fixed_mode' => 'per_line', 'is_progressive' => true, 'bracket_mode' => 'marginal', 'brackets' => [
        ['base_min' => '0', 'base_max' => '10', 'value' => '0'],
        ['base_min' => '10', 'base_max' => null, 'value' => '5'],
    ]], 'tax::exceptions.bracket_mode_not_allowed'],
    'non-progressive with a bounded bracket' => [['brackets' => [['base_min' => '0', 'base_max' => '10', 'value' => '18']]], 'tax::exceptions.last_bracket_must_be_open'],
    'progressive with one bracket'           => [['is_progressive' => true, 'bracket_mode' => 'whole_amount'], 'tax::exceptions.progressive_brackets_required'],
    'first bracket above zero'               => [['brackets' => [['base_min' => '5', 'base_max' => null, 'value' => '18']]], 'tax::exceptions.brackets_must_start_at_zero'],
    'gap between brackets'                   => [['is_progressive' => true, 'bracket_mode' => 'whole_amount', 'brackets' => [
        ['base_min' => '0', 'base_max' => '10', 'value' => '0'],
        ['base_min' => '20', 'base_max' => null, 'value' => '5'],
    ]], 'tax::exceptions.brackets_not_contiguous'],
    'overlapping brackets' => [['is_progressive' => true, 'bracket_mode' => 'whole_amount', 'brackets' => [
        ['base_min' => '0', 'base_max' => '100', 'value' => '0'],
        ['base_min' => '50', 'base_max' => null, 'value' => '5'],
    ]], 'tax::exceptions.brackets_not_contiguous'],
    'open bracket before the last' => [['is_progressive' => true, 'bracket_mode' => 'whole_amount', 'brackets' => [
        ['base_min' => '0', 'base_max' => null, 'value' => '0'],
        ['base_min' => '10', 'base_max' => null, 'value' => '5'],
    ]], 'tax::exceptions.only_last_bracket_may_be_open'],
    'inverted amount limits'               => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '10', 'tax_amount_max' => '5']]], 'tax::exceptions.bracket_amount_limits_inverted'],
    'per-line tax with a minimum amount'   => [['calculation_type' => 'fixed', 'fixed_mode' => 'per_line', 'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '200', 'tax_amount_min' => '100']]], 'tax::exceptions.bracket_amount_limits_not_allowed'],
    'per-line tax with a maximum amount'   => [['calculation_type' => 'fixed', 'fixed_mode' => 'per_line', 'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '200', 'tax_amount_max' => '500']]], 'tax::exceptions.bracket_amount_limits_not_allowed'],
    'decimals beyond the XOF precision'    => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '0.5']]], 'tax::exceptions.monetary_precision_exceeded'],
    'dependencies-only without dependency' => [['base_type' => 'dependencies_only'], 'tax::exceptions.dependency_required'],
    'unknown dependency'                   => [['dependencies' => [['depends_on_tax_id' => '0199f7a0-0000-7000-8000-000000000000', 'operator' => 'add']]], 'tax::exceptions.dependency_taxes_not_found'],
]);

it('refuses a duplicate code within the organization only', function (): void {
    $this->createPercentageTax('18', ['code' => 'VAT']);

    $this->postJson('/v1/tax/taxes', vatPayload())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.duplicate_tax_code', ['code' => 'VAT']));

    $firstOrganizationId = $this->organization->id;
    $this->setUpTaxTenant();
    $this->createPercentageTax('18', ['code' => 'VAT']);

    expect(Tax::query()->where('code', 'VAT')->whereIn('organization_id', [$firstOrganizationId, $this->organization->id])->count())->toBe(2);
});

it('refuses a dependency cycle and a self dependency', function (): void {
    $first = $this->createPercentageTax('1', ['code' => 'FIRST']);
    $second = $this->createPercentageTax('2', [
        'code'         => 'SECOND',
        'dependencies' => [['depends_on_tax_id' => $first->id, 'operator' => 'add']],
    ]);

    $this->putJson("/v1/tax/taxes/{$first->id}", vatPayload([
        'code'         => 'FIRST',
        'kind'         => 'other',
        'brackets'     => [['base_min' => '0', 'base_max' => null, 'value' => '1']],
        'dependencies' => [['depends_on_tax_id' => $second->id, 'operator' => 'add']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.dependency_cycle', ['cycle' => 'FIRST → SECOND → FIRST']))
        ->assertJsonPath('errors.context.tax_codes', ['FIRST', 'SECOND', 'FIRST']);

    $this->putJson("/v1/tax/taxes/{$first->id}", vatPayload([
        'code'         => 'FIRST',
        'dependencies' => [['depends_on_tax_id' => $first->id, 'operator' => 'add']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.self_dependency'));
});

it('replaces the own grid of a tax and leaves the custom grids of profiles as they are', function (): void {
    $tax = $this->createTax([
        'code'           => 'PROG',
        'is_progressive' => true,
        'bracket_mode'   => 'whole_amount',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => null, 'value' => '5'],
        ],
    ]);
    $profile = $this->createProfile([[
        'tax_id'    => $tax->id,
        'treatment' => 'custom',
        'brackets'  => [
            ['base_min' => '0', 'base_max' => '5000', 'value' => '0'],
            ['base_min' => '5000', 'base_max' => null, 'value' => '3'],
        ],
    ]]);

    $this->putJson("/v1/tax/taxes/{$tax->id}", vatPayload([
        'code'           => 'PROG',
        'kind'           => 'other',
        'is_progressive' => true,
        'bracket_mode'   => 'whole_amount',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '20000', 'value' => '1'],
            ['base_min' => '20000', 'base_max' => null, 'value' => '6'],
        ],
    ]))->assertNoContent();

    $customGrid = $profile->lines()->firstOrFail()->brackets()->get();

    expect($tax->brackets()->pluck('base_max')->all())->toBe(['20000.000000', null])
        ->and($tax->brackets()->pluck('value')->all())->toBe(['1.000000', '6.000000'])
        ->and($customGrid->pluck('base_max')->all())->toBe(['5000.000000', null])
        ->and($customGrid->pluck('value')->all())->toBe(['0.000000', '3.000000']);
});

it('keeps a tax progressive or not while a profile gives it a custom grid', function (): void {
    $tax = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']], ['code' => 'STD']);
    $custom = $this->createProfile([[
        'tax_id'    => $tax->id,
        'treatment' => 'custom',
        'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '10']],
    ]], ['code' => 'REDUCED']);
    $progressive = vatPayload(['is_progressive' => true, 'bracket_mode' => 'marginal', 'brackets' => [
        ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
        ['base_min' => '10000', 'base_max' => null, 'value' => '18'],
    ]]);

    $this->putJson("/v1/tax/taxes/{$tax->id}", $progressive)
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_progression_locked'))
        ->assertJsonPath('errors.context.tax_profile_codes', ['REDUCED']);

    $this->deleteJson("/v1/tax/profiles/{$custom->id}")->assertNoContent();

    $this->putJson("/v1/tax/taxes/{$tax->id}", $progressive)->assertNoContent();

    expect($tax->refresh()->is_progressive)->toBeTrue();
});

it('protects taxes used by profiles or by other taxes', function (): void {
    $eco = $this->createPercentageTax('1', ['code' => 'ECO']);
    $vat = $this->createPercentageTax('18', [
        'code'         => 'VAT',
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]);
    $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard']], ['code' => 'STD']);

    $this->deleteJson("/v1/tax/taxes/{$eco->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.context.tax_codes', ['VAT']);

    $this->deleteJson("/v1/tax/taxes/{$vat->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.context.tax_profile_codes', ['STD']);

    $this->putJson("/v1/tax/taxes/{$vat->id}", vatPayload([
        'is_active' => false,
        'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '18']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_used_by_active_profiles'));

    $this->putJson("/v1/tax/taxes/{$vat->id}", vatPayload([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '18']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_structure_locked'));
});

it('keeps dependencies-only taxes complete in the profiles using them', function (): void {
    $vat = $this->createPercentageTax('17.5', ['code' => 'VAT']);
    $other = $this->createPercentageTax('3', ['code' => 'OTHER']);
    $cac = $this->createPercentageTax('10', [
        'code'         => 'CAC',
        'base_type'    => 'dependencies_only',
        'dependencies' => [['depends_on_tax_id' => $vat->id, 'operator' => 'add']],
    ]);
    $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard'], ['tax_id' => $cac->id, 'treatment' => 'standard']], ['code' => 'CMR']);

    $this->putJson("/v1/tax/taxes/{$cac->id}", vatPayload([
        'code'         => 'CAC',
        'kind'         => 'other',
        'base_type'    => 'dependencies_only',
        'brackets'     => [['base_min' => '0', 'base_max' => null, 'value' => '10']],
        'dependencies' => [
            ['depends_on_tax_id' => $vat->id, 'operator' => 'add'],
            ['depends_on_tax_id' => $other->id, 'operator' => 'add'],
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.context.tax_profile_codes', ['CMR']);
});

it('hides the taxes of another organization', function (): void {
    $foreignTax = Tax::factory()->withValue('18')->create();

    $this->getJson("/v1/tax/taxes/{$foreignTax->id}")->assertForbidden();
    $this->getJson('/v1/tax/taxes')->assertOk()->assertJsonCount(0, 'data');
});

it('scopes the relations of a tax to the current organization', function (): void {
    $foreignTax = Tax::factory()->withValue('18')->create();
    $ownTax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);

    // The foreign bracket exists, but a relation followed in this organization does not reach it.
    expect(TaxBracket::query()->where('tax_id', $foreignTax->id)->count())->toBe(1)
        ->and($foreignTax->brackets()->count())->toBe(0)
        ->and($ownTax->brackets()->count())->toBe(1)
        ->and($ownTax->taxUnit?->code)->toBe('kg');
});

it('requires the tax permissions', function (): void {
    $this->authenticateTaxMember(['tax_tax.list']);

    $this->getJson('/v1/tax/taxes')->assertOk();
    $this->postJson('/v1/tax/taxes', vatPayload())->assertForbidden();
});

it('requires the tax permissions to change or delete a tax', function (): void {
    $tax = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $bracketId = $tax->brackets()->firstOrFail()->id;
    $this->authenticateTaxMember(['tax_tax.list', 'tax_tax.retrieve']);

    $this->putJson("/v1/tax/taxes/{$tax->id}", vatPayload([
        'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '20']],
    ]))->assertForbidden();
    $this->deleteJson("/v1/tax/taxes/{$tax->id}")->assertForbidden();

    expect(Tax::query()->whereKey($tax->id)->exists())->toBeTrue()
        ->and(TaxBracket::query()->whereKey($bracketId)->value('value'))->toBe('18.000000');
});

it('refuses an unauthenticated request', function (): void {
    $this->getJson('/v1/tax/taxes', ['Authorization' => ''])->assertUnauthorized();
});

it('refuses a dependency on a tax of another organization', function (): void {
    $foreignTax = Tax::factory()->create();

    $this->postJson('/v1/tax/taxes', vatPayload([
        'dependencies' => [['depends_on_tax_id' => $foreignTax->id, 'operator' => 'add']],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.dependency_taxes_not_found'))
        ->assertJsonPath('errors.context.depends_on_tax_ids', [$foreignTax->id]);

    expect(Tax::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse();
});

it('refuses to change or delete a tax of another organization', function (): void {
    $foreignTax = Tax::factory()->create(['code' => 'FOREIGN', 'name' => 'Foreign tax']);
    $foreignBracket = TaxBracket::query()->where('tax_id', $foreignTax->id)->firstOrFail();

    $this->putJson("/v1/tax/taxes/{$foreignTax->id}", vatPayload([
        'code'     => 'STOLEN',
        'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '1']],
    ]))->assertForbidden();
    $this->deleteJson("/v1/tax/taxes/{$foreignTax->id}")->assertForbidden();

    $foreignTax->refresh();

    expect($foreignTax->code)->toBe('FOREIGN')
        ->and($foreignTax->name)->toBe('Foreign tax')
        ->and($foreignTax->deleted_at)->toBeNull()
        ->and($foreignBracket->refresh()->value)->toBe('18.000000');
});
