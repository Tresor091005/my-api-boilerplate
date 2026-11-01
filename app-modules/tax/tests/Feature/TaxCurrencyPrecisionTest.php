<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

/** Amounts in these tests are euro cents: the functional currency is EUR (2 decimals). */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['functional_currency_code' => 'EUR']);
    setPermissionsTeamId($this->organization->id);
    $this->engine = app(TaxInterface::class);
});

/** @param array<string, mixed> $overrides */
function euroLine(object $test, string $taxProfileId, string $baseAmount, array $overrides = []): TaxableLineData
{
    return $test->taxableLine($taxProfileId, $baseAmount, ['currency_code' => 'EUR', ...$overrides]);
}

it('charges a per-litre rate finer than the currency on the converted quantity', function (): void {
    $tax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'l',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '0.6069']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve(euroLine($this, $profile->id, '1000000', [
        'unit_group_id' => app(MasterInterface::class)->unit('l')->group_id,
        'quantity'      => '10000',
        'unit_code'     => 'l',
    ]))->toArray();

    expect($result['taxes'][0])->toMatchArray(['tax_quantity' => '10000', 'tax_amount' => '606900'])
        ->and($result['taxes'][0]['brackets'][0]['value'])->toBe('60.69');
});

it('accepts a per-unit override finer than the currency', function (): void {
    $tax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'l',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '0.6069']],
    ]);
    $profile = $this->createProfile([[
        'tax_id'            => $tax->id,
        'treatment'         => 'custom',
        'bracket_overrides' => [['tax_bracket_id' => $tax->brackets()->firstOrFail()->id, 'value' => '0.1234']],
    ]]);

    $result = $this->engine->resolve(euroLine($this, $profile->id, '1000000', [
        'unit_group_id' => app(MasterInterface::class)->unit('l')->group_id,
        'quantity'      => '500',
        'unit_code'     => 'l',
    ]));

    expect($result->totalTaxAmount)->toBe('6170');
});

it('refuses a per-line override finer than the currency but accepts a per-unit one', function (): void {
    $perLine = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '2.50']],
    ]);
    $perUnit = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'l',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '0.6069']],
    ]);

    try {
        $this->createProfile([[
            'tax_id'            => $perLine->id,
            'treatment'         => 'custom',
            'bracket_overrides' => [['tax_bracket_id' => $perLine->brackets()->firstOrFail()->id, 'value' => '1.255']],
        ]]);
        $this->fail('The override was accepted.');
    } catch (TaxException $exception) {
        expect($exception->getMessage())->toBe(__('tax::exceptions.override_precision_exceeded', ['code' => $perLine->code, 'precision' => 2]))
            ->and($exception->context())->toMatchArray(['tax_code' => $perLine->code, 'precision' => 2]);
    }

    $profile = $this->createProfile([[
        'tax_id'            => $perUnit->id,
        'treatment'         => 'custom',
        'bracket_overrides' => [['tax_bracket_id' => $perUnit->brackets()->firstOrFail()->id, 'value' => '0.1234']],
    ]]);

    expect($profile->exists)->toBeTrue();
});

it('refuses monetary values with more decimals than the currency outside per-unit rates', function (array $bracket, string $field): void {
    try {
        $this->createTax([
            'calculation_type' => 'fixed',
            'fixed_mode'       => 'per_line',
            'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '1.00', ...$bracket]],
        ]);
        $this->fail('The value was accepted.');
    } catch (TaxException $exception) {
        expect($exception->getMessage())->toBe(__('tax::exceptions.monetary_precision_exceeded', ['precision' => 2]))
            ->and($exception->context())->toMatchArray(['field' => $field, 'precision' => 2]);
    }
})->with([
    'per-line fixed value' => [['value' => '1.255'], 'value'],
    'minimum tax amount'   => [['tax_amount_min' => '0.001'], 'tax_amount_min'],
    'maximum tax amount'   => [['tax_amount_max' => '9.999'], 'tax_amount_max'],
]);

it('refuses amount limits and bracket bounds finer than the currency on a per-unit tax', function (array $brackets, string $field): void {
    try {
        $this->createTax([
            'calculation_type' => 'fixed',
            'fixed_mode'       => 'per_tax_unit',
            'tax_unit_code'    => 'l',
            'is_progressive'   => count($brackets) > 1,
            'brackets'         => $brackets,
        ]);
        $this->fail('The value was accepted.');
    } catch (TaxException $exception) {
        expect($exception->getMessage())->toBe(__('tax::exceptions.monetary_precision_exceeded', ['precision' => 2]))
            ->and($exception->context())->toMatchArray(['field' => $field, 'precision' => 2]);
    }
})->with([
    'minimum tax amount' => [[['base_min' => '0', 'base_max' => null, 'value' => '0.6069', 'tax_amount_min' => '0.001']], 'tax_amount_min'],
    'maximum tax amount' => [[['base_min' => '0', 'base_max' => null, 'value' => '0.6069', 'tax_amount_max' => '9.999']], 'tax_amount_max'],
    'bracket bound'      => [[
        ['base_min' => '0', 'base_max' => '0.001', 'value' => '0.6069'],
        ['base_min' => '0.001', 'base_max' => null, 'value' => '0.7'],
    ], 'base_max'],
]);

it('charges a per-line fixed amount in cents', function (): void {
    $tax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '2.50']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve(euroLine($this, $profile->id, '10000'))->totalTaxAmount)->toBe('250');
});

it('converts progressive bracket bounds to cents', function (): void {
    $tax = $this->createTax([
        'is_progressive' => true,
        'bracket_mode'   => 'marginal',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '100.50', 'value' => '0'],
            ['base_min' => '100.50', 'base_max' => null, 'value' => '10'],
        ],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve(euroLine($this, $profile->id, '15000'))->toArray();

    expect($result['total_tax_amount'])->toBe('495')
        ->and(collect($result['taxes'][0]['brackets'])->map(fn (array $bracket): array => [$bracket['base_min'], $bracket['base_max'], $bracket['taxable_portion'], $bracket['tax_amount']])->all())
        ->toBe([['0', '10050', '10050', '0'], ['10050', null, '4950', '495']]);
});

it('applies minimum and maximum tax amounts in cents', function (string $base, string $expected): void {
    $tax = $this->createTax([
        'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '1', 'tax_amount_min' => '5.50', 'tax_amount_max' => '8.25']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve(euroLine($this, $profile->id, $base))->totalTaxAmount)->toBe($expected);
})->with([
    'below the minimum' => ['10000', '550'],
    'between limits'    => ['60000', '600'],
    'above the maximum' => ['100000', '825'],
]);

it('rounds to a whole cent with the rounding mode of the tax', function (string $roundingMode, string $base, string $expected): void {
    $tax = $this->createPercentageTax('18', ['rounding_mode' => $roundingMode]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve(euroLine($this, $profile->id, $base))->totalTaxAmount)->toBe($expected);
})->with([
    'half up, exact half' => ['half_up', '1025', '185'],
    'up'                  => ['up', '1001', '181'],
    'down'                => ['down', '1003', '180'],
]);

it('accepts a per-unit rate finer than the currency through the API', function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->authenticateTaxMember();

    $this->postJson('/v1/tax/taxes?response=resource&include=brackets', [
        'code'             => 'fuel',
        'name'             => 'Fuel levy',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'l',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '0.6069']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.fixed_mode', 'per_tax_unit')
        ->assertJsonPath('data.brackets.0.value', '0.606900');
});
