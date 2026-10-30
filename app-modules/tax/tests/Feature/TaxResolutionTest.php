<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Services\TaxService;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->setUpTaxTenant();
    $this->engine = app(TaxInterface::class);
});

/** @return array<string, mixed> */
function taxesByCode(array $result): array
{
    return collect($result['taxes'])->keyBy('code')->all();
}

it('returns no tax for a line without profile', function (): void {
    $result = $this->engine->resolve($this->taxableLine(null, '10000'))->toArray();

    expect($result['taxes'])->toBe([])
        ->and($result['total_tax_amount'])->toBe('0')
        ->and($result['schema_version'])->toBe(1);
});

it('applies a simple percentage', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $profile = $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray();

    expect($result['total_tax_amount'])->toBe('1800')
        ->and($result['taxes'][0])->toMatchArray([
            'code'                => 'VAT',
            'kind'                => 'vat',
            'treatment'           => 'standard',
            'input_base_amount'   => '10000',
            'dependency_amount'   => '0',
            'taxable_base_amount' => '10000',
            'tax_amount'          => '1800',
        ]);
});

it('applies a fixed amount per line', function (): void {
    $eco = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $profile = $this->createProfile([['tax_id' => $eco->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve($this->taxableLine($profile->id, '10000'))->totalTaxAmount)->toBe('500');
});

it('charges a fixed amount per tax unit on the quantity converted to that unit', function (): void {
    $massGroupId = app(MasterInterface::class)->unit('kg')->group_id;
    $tax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '2500', [
        'unit_group_id' => $massGroupId,
        'quantity'      => '500',
        'unit_code'     => 'g',
    ]))->toArray();

    expect($result['taxes'][0])->toMatchArray([
        'tax_quantity'  => '0.5',
        'tax_unit_code' => 'kg',
        'tax_amount'    => '50',
    ]);
});

it('rejects per-unit taxes whose units cannot apply to the line', function (array $line, string $expectedKey): void {
    $tax = $this->createTax([
        'code'             => 'PER-KG',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);
    $groups = [
        'mass'   => app(MasterInterface::class)->unit('kg')->group_id,
        'volume' => app(MasterInterface::class)->unit('l')->group_id,
    ];
    $line['unit_group_id'] = $groups[$line['unit_group_id']];

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '1000', $line)))
        ->toThrow(TaxException::class, __($expectedKey, ['code' => 'PER-KG', 'unit' => $line['unit_code'] ?? '']));
})->with([
    'sale unit missing'              => [['unit_group_id' => 'mass', 'unit_code' => null], 'tax::exceptions.sale_unit_required'],
    'sale unit of another group'     => [['unit_group_id' => 'mass', 'unit_code' => 'l'], 'tax::exceptions.sale_unit_incompatible'],
    'item measured in another group' => [['unit_group_id' => 'volume', 'unit_code' => 'l'], 'tax::exceptions.tax_unit_incompatible'],
]);

it('applies whole-amount and marginal progressive percentages', function (string $bracketMode, string $expectedTotal, array $expectedPortions): void {
    $tax = $this->createTax([
        'is_progressive' => true,
        'bracket_mode'   => $bracketMode,
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => '50000', 'value' => '5'],
            ['base_min' => '50000', 'base_max' => null, 'value' => '10'],
        ],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '60000'))->toArray();

    expect($result['total_tax_amount'])->toBe($expectedTotal)
        ->and(collect($result['taxes'][0]['brackets'])->map(fn (array $bracket): array => [$bracket['taxable_portion'], $bracket['tax_amount']])->all())
        ->toBe($expectedPortions);
})->with([
    'whole amount' => ['whole_amount', '6000', [['60000', '6000']]],
    'marginal'     => ['marginal', '3000', [['10000', '0'], ['40000', '2000'], ['10000', '1000']]],
]);

it('includes the lower bound and excludes the upper bound of a bracket', function (string $base, string $expected): void {
    $tax = $this->createTax([
        'is_progressive' => true,
        'bracket_mode'   => 'whole_amount',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => null, 'value' => '5'],
        ],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve($this->taxableLine($profile->id, $base))->totalTaxAmount)->toBe($expected);
})->with([
    'just below the bound' => ['9999', '0'],
    'on the bound'         => ['10000', '500'],
]);

it('selects the fixed amount of the bracket containing the base for a progressive fixed tax', function (): void {
    $tax = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'is_progressive'   => true,
        'brackets'         => [
            ['base_min' => '0', 'base_max' => '50000', 'value' => '200'],
            ['base_min' => '50000', 'base_max' => null, 'value' => '1000'],
        ],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve($this->taxableLine($profile->id, '60000'))->totalTaxAmount)->toBe('1000');
});

it('replaces only the overridden bracket value for a custom treatment', function (): void {
    $vat = $this->createPercentageTax('18');
    $profile = $this->createProfile([[
        'tax_id'            => $vat->id,
        'treatment'         => 'custom',
        'bracket_overrides' => [['tax_bracket_id' => $vat->brackets()->firstOrFail()->id, 'value' => '5']],
    ]]);

    $tax = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray()['taxes'][0];

    expect($tax['tax_amount'])->toBe('500')
        ->and($tax['brackets'][0])->toMatchArray(['value' => '5', 'is_overridden' => true]);
});

it('keeps zero-rated and exempt taxes as distinct zero lines', function (): void {
    $zeroRated = $this->createPercentageTax('18', ['code' => 'ZERO']);
    $exempted = $this->createPercentageTax('5', ['code' => 'EXEMPT']);
    $profile = $this->createProfile([
        ['tax_id' => $zeroRated->id, 'treatment' => 'zero'],
        ['tax_id' => $exempted->id, 'treatment' => 'exempt'],
    ]);

    $taxes = taxesByCode($this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray());

    expect($taxes['ZERO'])->toMatchArray(['treatment' => 'zero', 'tax_amount' => '0', 'brackets' => []])
        ->and($taxes['EXEMPT'])->toMatchArray(['treatment' => 'exempt', 'tax_amount' => '0', 'brackets' => []]);
});

it('adds a dependency amount to the taxable base', function (): void {
    $eco = $this->createTax([
        'code'             => 'ECO',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $vat = $this->createPercentageTax('18', [
        'code'         => 'VAT',
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $vat->id, 'treatment' => 'standard'],
        ['tax_id' => $eco->id, 'treatment' => 'standard'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray();

    expect(collect($result['taxes'])->pluck('code')->all())->toBe(['ECO', 'VAT'])
        ->and(taxesByCode($result)['VAT'])->toMatchArray([
            'dependency_amount'   => '500',
            'taxable_base_amount' => '10500',
            'tax_amount'          => '1890',
            'dependencies'        => [['tax_id' => $eco->id, 'code' => 'ECO', 'operator' => 'add', 'tax_amount' => '500']],
        ])
        ->and($result['total_tax_amount'])->toBe('2390');
});

it('counts a zero-rated or absent dependency as nothing', function (?string $ecoTreatment): void {
    $eco = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $vat = $this->createPercentageTax('18', [
        'code'         => 'VAT',
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]);
    $lines = [['tax_id' => $vat->id, 'treatment' => 'standard']];

    if ($ecoTreatment !== null) {
        $lines[] = ['tax_id' => $eco->id, 'treatment' => $ecoTreatment];
    }

    $profile = $this->createProfile($lines);
    $result = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray();

    expect(taxesByCode($result)['VAT'])->toMatchArray(['dependency_amount' => '0', 'tax_amount' => '1800']);
})->with([
    'zero-rated dependency'          => ['zero'],
    'exempt dependency'              => ['exempt'],
    'dependency absent from profile' => [null],
]);

it('subtracts a dependency amount from the taxable base', function (): void {
    $rebate = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '1000']],
    ]);
    $tax = $this->createPercentageTax('10', [
        'code'         => 'NET',
        'dependencies' => [['depends_on_tax_id' => $rebate->id, 'operator' => 'subtract']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $tax->id, 'treatment' => 'standard'],
        ['tax_id' => $rebate->id, 'treatment' => 'standard'],
    ]);

    expect(taxesByCode($this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray())['NET'])
        ->toMatchArray(['dependency_amount' => '-1000', 'taxable_base_amount' => '9000', 'tax_amount' => '900']);
});

it('refuses a dependency that makes the taxable base negative', function (): void {
    $rebate = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '20000']],
    ]);
    $tax = $this->createPercentageTax('10', [
        'code'         => 'NET',
        'dependencies' => [['depends_on_tax_id' => $rebate->id, 'operator' => 'subtract']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $tax->id, 'treatment' => 'standard'],
        ['tax_id' => $rebate->id, 'treatment' => 'standard'],
    ]);

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '10000')))
        ->toThrow(TaxException::class, __('tax::exceptions.negative_taxable_base', ['code' => 'NET']));
});

it('computes a tax on its dependencies only, like the Cameroon additional council tax', function (): void {
    $vat = $this->createPercentageTax('17.5', ['code' => 'VAT', 'kind' => 'vat']);
    $cac = $this->createPercentageTax('10', [
        'code'         => 'CAC',
        'base_type'    => 'dependencies_only',
        'dependencies' => [['depends_on_tax_id' => $vat->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $vat->id, 'treatment' => 'standard'],
        ['tax_id' => $cac->id, 'treatment' => 'standard'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray();

    expect(taxesByCode($result)['CAC'])->toMatchArray([
        'input_base_amount'   => '0',
        'dependency_amount'   => '1750',
        'taxable_base_amount' => '1750',
        'tax_amount'          => '175',
    ])->and($result['total_tax_amount'])->toBe('1925');
});

it('applies the bracket floor and ceiling to the calculated amount', function (string $base, string $expected): void {
    $tax = $this->createTax([
        'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '1', 'tax_amount_min' => '500', 'tax_amount_max' => '800']],
    ]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve($this->taxableLine($profile->id, $base))->totalTaxAmount)->toBe($expected);
})->with([
    'below the floor'   => ['10000', '500'],
    'between limits'    => ['60000', '600'],
    'above the ceiling' => ['100000', '800'],
]);

it('rounds each bracket with the rounding mode of the tax', function (string $roundingMode, string $base, string $expected): void {
    $tax = $this->createPercentageTax('18', ['rounding_mode' => $roundingMode]);
    $profile = $this->createProfile([['tax_id' => $tax->id, 'treatment' => 'standard']]);

    expect($this->engine->resolve($this->taxableLine($profile->id, $base))->totalTaxAmount)->toBe($expected);
})->with([
    'half up, fraction above half' => ['half_up', '1003', '181'],
    'half up, fraction below half' => ['half_up', '1001', '180'],
    'half up, exact half'          => ['half_up', '1025', '185'],
    'up'                           => ['up', '1001', '181'],
    'down'                         => ['down', '1003', '180'],
]);

it('reproduces the snapshot example of the decision record', function (): void {
    $eco = $this->createTax([
        'code'             => 'ECO',
        'name'             => 'Écotaxe',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $vat = $this->createTax([
        'code'           => 'VAT',
        'name'           => 'TVA',
        'kind'           => 'vat',
        'is_progressive' => true,
        'bracket_mode'   => 'marginal',
        'brackets'       => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => '50000', 'value' => '5'],
            ['base_min' => '50000', 'base_max' => null, 'value' => '10'],
        ],
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => 'add']],
    ]);
    $telecom = $this->createPercentageTax('7', ['code' => 'TELECOM', 'name' => 'Taxe télécom']);
    $middleBracket = $vat->brackets()->where('base_min', '10000')->firstOrFail();
    $profile = $this->createProfile([
        ['tax_id' => $eco->id, 'treatment' => 'standard'],
        ['tax_id' => $vat->id, 'treatment' => 'custom', 'bracket_overrides' => [['tax_bracket_id' => $middleBracket->id, 'value' => '6']]],
        ['tax_id' => $telecom->id, 'treatment' => 'exempt'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '60000'))->toArray();
    $taxes = taxesByCode($result);

    expect($result['total_tax_amount'])->toBe('3950')
        ->and($taxes['ECO']['tax_amount'])->toBe('500')
        ->and($taxes['VAT'])->toMatchArray([
            'treatment'           => 'custom',
            'bracket_mode'        => 'marginal',
            'input_base_amount'   => '60000',
            'dependency_amount'   => '500',
            'taxable_base_amount' => '60500',
            'tax_amount'          => '3450',
        ])
        ->and(collect($taxes['VAT']['brackets'])->map(fn (array $bracket): array => [$bracket['value'], $bracket['taxable_portion'], $bracket['tax_amount']])->all())
        ->toBe([['0', '10000', '0'], ['6', '40000', '2400'], ['10', '10500', '1050']])
        ->and($taxes['TELECOM'])->toMatchArray(['treatment' => 'exempt', 'tax_amount' => '0']);

    foreach ($result['taxes'] as $tax) {
        $signedDependencies = collect($tax['dependencies'])->reduce(
            fn (string $sum, array $dependency): string => $dependency['operator'] === 'add' ? bcadd($sum, $dependency['tax_amount']) : bcsub($sum, $dependency['tax_amount']),
            '0',
        );
        expect(bcadd($tax['input_base_amount'], $tax['dependency_amount']))->toBe($tax['taxable_base_amount'])
            ->and($tax['dependency_amount'])->toBe($signedDependencies);
    }
});

it('refuses another currency than the functional currency', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->createPercentageTax('18')->id, 'treatment' => 'standard']]);

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '10000', ['currency_code' => 'EUR'])))
        ->toThrow(TaxException::class, __('tax::exceptions.currency_mismatch', ['currency' => 'EUR', 'functional' => 'XOF']));
});

it('refuses an inactive or foreign profile', function (): void {
    $inactive = $this->createProfile(
        [['tax_id' => $this->createPercentageTax('18')->id, 'treatment' => 'standard']],
        ['code' => 'OFF', 'is_active' => false],
    );

    expect(fn () => $this->engine->resolve($this->taxableLine($inactive->id, '100')))
        ->toThrow(TaxException::class, __('tax::exceptions.profile_inactive', ['code' => 'OFF']));

    $foreignProfileId = $inactive->id;
    $this->setUpTaxTenant();

    expect(fn () => $this->engine->resolve($this->taxableLine($foreignProfileId, '100')))
        ->toThrow(TaxException::class, __('tax::exceptions.profile_not_found'));
});

it('uses the new configuration once a tax changes', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $profile = $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard']]);
    $line = $this->taxableLine($profile->id, '10000');

    expect($this->engine->resolve($line)->totalTaxAmount)->toBe('1800');

    app(TaxService::class)->update($vat, TaxData::fromArray([
        'code'             => 'VAT',
        'name'             => 'VAT',
        'calculation_type' => 'percentage',
        'brackets'         => [['id' => $vat->brackets()->firstOrFail()->id, 'base_min' => '0', 'base_max' => null, 'value' => '20']],
    ]));

    expect(app(TaxInterface::class)->resolve($line)->totalTaxAmount)->toBe('2000');
});

it('resolves many lines with a number of queries independent of their count', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $eco = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $profiles = [
        $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard']]),
        $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard'], ['tax_id' => $eco->id, 'treatment' => 'standard']]),
    ];
    $countQueries = function (int $lineCount) use ($profiles): int {
        app()->forgetScopedInstances();
        cache()->flush();
        $lines = collect(range(1, $lineCount))->mapWithKeys(fn (int $index): array => [
            "line-{$index}" => $this->taxableLine($profiles[$index % 2]->id, (string) ($index * 1000)),
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $results = app(TaxInterface::class)->resolveMany($lines);
        DB::disableQueryLog();

        expect($results->keys()->all())->toBe($lines->keys()->all())
            ->and($results['line-2']->totalTaxAmount)->toBe('360');

        return count(DB::getQueryLog());
    };

    expect($countQueries(30))->toBe($countQueries(4));
});

it('produces the same result for the same configuration and inputs', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $other = $this->createPercentageTax('2', ['code' => 'AAA']);
    $profile = $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard'], ['tax_id' => $other->id, 'treatment' => 'standard']]);

    $first = $this->engine->resolve($this->taxableLine($profile->id, '12345'))->toArray();
    app()->forgetScopedInstances();
    cache()->flush();
    $second = app(TaxInterface::class)->resolve($this->taxableLine($profile->id, '12345'))->toArray();

    expect($first)->toBe($second)
        ->and(collect($first['taxes'])->pluck('code')->all())->toBe(['AAA', 'VAT']);
});

it('checks that a profile can be assigned to items of given unit groups', function (): void {
    $massGroupId = app(MasterInterface::class)->unit('kg')->group_id;
    $volumeGroupId = app(MasterInterface::class)->unit('l')->group_id;
    $perKg = $this->createTax([
        'code'             => 'PER-KG',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perKg->id, 'treatment' => 'standard']]);

    DB::transaction(fn () => $this->engine->assertProfileAssignable($profile->id, collect([$massGroupId])));

    expect(fn () => DB::transaction(fn () => $this->engine->assertProfileAssignable($profile->id, collect([$volumeGroupId]))))
        ->toThrow(TaxException::class, __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']));
});

it('ignores per-unit taxes that do not apply when checking an assignment', function (): void {
    $perKg = $this->createTax([
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perKg->id, 'treatment' => 'exempt']]);

    DB::transaction(fn () => $this->engine->assertProfileAssignable($profile->id, collect([app(MasterInterface::class)->unit('l')->group_id])));

    expect(Tax::query()->count())->toBeGreaterThan(0);
});

it('reuses compiled profiles from a serializing cache store until the configuration changes', function (): void {
    config(['cache.default' => 'database']);
    app()->forgetInstance('cache');
    app()->forgetInstance('cache.store');
    Cache::clearResolvedInstances();
    app()->forgetScopedInstances();
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $profile = $this->createProfile([['tax_id' => $vat->id, 'treatment' => 'standard']]);
    $line = $this->taxableLine($profile->id, '10000');
    $resolveInNewRequest = function () use ($line): array {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $total = app(TaxInterface::class)->resolve($line)->totalTaxAmount;
        DB::disableQueryLog();

        return [$total, collect(DB::getQueryLog())->contains(fn (array $query): bool => str_contains($query['query'], 'tax_profiles'))];
    };

    expect($resolveInNewRequest())->toBe(['1800', true])
        ->and($resolveInNewRequest())->toBe(['1800', false]);

    app(TaxService::class)->update($vat, TaxData::fromArray([
        'code'             => 'VAT',
        'name'             => 'VAT',
        'calculation_type' => 'percentage',
        'brackets'         => [['id' => $vat->brackets()->firstOrFail()->id, 'base_min' => '0', 'base_max' => null, 'value' => '20']],
    ]));

    expect($resolveInNewRequest())->toBe(['2000', true]);
});
