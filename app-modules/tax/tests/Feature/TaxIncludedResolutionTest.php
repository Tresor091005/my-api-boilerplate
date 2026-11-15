<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\IncludedTaxAmountData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->setUpTaxTenant();
    $this->engine = app(TaxInterface::class);
    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
});

/** @return array<string, array<string, mixed>> */
function includedTaxesByCode(array $result): array
{
    return collect($result['taxes'])->keyBy('code')->all();
}

it('takes the contained value-added tax out of an amount', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '11800', ['included_tax_ids' => [$this->vat->id]]))->toArray();

    expect($result)->toMatchArray([
        'schema_version'      => 2,
        'amount'              => '11800',
        'base_amount'         => '10000',
        'included_tax_amount' => '1800',
        'added_tax_amount'    => '0',
        'total_tax_amount'    => '1800',
        'total_amount'        => '11800',
    ])->and($result['taxes'][0])->toMatchArray([
        'code'                => 'VAT',
        'is_included'         => true,
        'input_base_amount'   => '10000',
        'taxable_base_amount' => '10000',
        'rounding_adjustment' => '0',
        'tax_amount'          => '1800',
    ]);
});

it('treats an amount that contains no tax as the base, every tax coming on top', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '10000'))->toArray();

    expect($result)->toMatchArray([
        'amount'              => '10000',
        'base_amount'         => '10000',
        'included_tax_amount' => '0',
        'added_tax_amount'    => '1800',
        'total_tax_amount'    => '1800',
        'total_amount'        => '11800',
    ])->and($result['taxes'][0])->toMatchArray(['is_included' => false, 'rounding_adjustment' => '0']);
});

it('adds on top the taxes the amount does not contain', function (): void {
    $levy = $this->createPercentageTax('5', ['code' => 'LEVY']);
    $profile = $this->createProfile([
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $levy->id, 'treatment' => 'standard'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '11800', ['included_tax_ids' => [$this->vat->id]]))->toArray();
    $taxes = includedTaxesByCode($result);

    expect($result)->toMatchArray([
        'amount'              => '11800',
        'base_amount'         => '10000',
        'included_tax_amount' => '1800',
        'added_tax_amount'    => '500',
        'total_tax_amount'    => '2300',
        'total_amount'        => '12300',
    ])->and($taxes['VAT'])->toMatchArray(['is_included' => true, 'tax_amount' => '1800'])
        ->and($taxes['LEVY'])->toMatchArray(['is_included' => false, 'taxable_base_amount' => '10000', 'tax_amount' => '500']);
});

it('keeps the amount to the unit when rounding leaves no whole base, the tax taking the difference', function (string $amount, string $base, string $calculated, string $adjustment, string $kept): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, $amount, ['included_tax_ids' => [$this->vat->id]]))->toArray();

    expect($result['base_amount'])->toBe($base)
        ->and($result['taxes'][0]['brackets'][0]['tax_amount'])->toBe($calculated)
        ->and($result['taxes'][0]['rounding_adjustment'])->toBe($adjustment)
        ->and($result['taxes'][0]['tax_amount'])->toBe($kept)
        ->and(bcadd($result['base_amount'], $result['included_tax_amount'], 0))->toBe($amount)
        ->and($result['total_amount'])->toBe($amount);
})->with([
    '121 is 102.54 before tax: 103 and 18'   => ['121', '103', '19', '-1', '18'],
    '1000 is 847.46 before tax: 847 and 153' => ['1000', '847', '152', '1', '153'],
]);

it('takes out a per-unit tax and the value-added tax calculated on it', function (): void {
    $excise = $this->createTax([
        'code'             => 'EXCISE',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'l',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '60.69']],
    ]);
    $vatOnExcise = $this->createPercentageTax('18', [
        'code'         => 'VAT-EXCISE',
        'dependencies' => [['depends_on_tax_id' => $excise->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $excise->id, 'treatment' => 'standard'],
        ['tax_id' => $vatOnExcise->id, 'treatment' => 'standard'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '1500', [
        'included_tax_ids' => [$excise->id, $vatOnExcise->id],
        'quantity'         => '3',
        'unit_code'        => 'l',
    ]))->toArray();
    $taxes = includedTaxesByCode($result);

    // 3 l × 60.69 = 182 of excise, 18% of (1089 + 182) = 229: 1089 + 182 + 229 = 1500
    expect($result['base_amount'])->toBe('1089')
        ->and($taxes['EXCISE']['tax_amount'])->toBe('182')
        ->and($taxes['VAT-EXCISE'])->toMatchArray(['taxable_base_amount' => '1271', 'tax_amount' => '229'])
        ->and($result['included_tax_amount'])->toBe('411')
        ->and($result['total_amount'])->toBe('1500');
});

it('lets a tax added on top be calculated on the amount displayed', function (): void {
    $levy = $this->createPercentageTax('5', [
        'code'         => 'LEVY',
        'dependencies' => [['depends_on_tax_id' => $this->vat->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $levy->id, 'treatment' => 'standard'],
    ]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '121', ['included_tax_ids' => [$this->vat->id]]))->toArray();
    $taxes = includedTaxesByCode($result);

    expect($taxes['VAT']['tax_amount'])->toBe('18')
        ->and($taxes['LEVY'])->toMatchArray(['taxable_base_amount' => '121', 'tax_amount' => '6'])
        ->and($result['total_amount'])->toBe('127');
});

it('leaves out a contained tax that is not in the profile', function (): void {
    $otherTax = $this->createPercentageTax('5', ['code' => 'OTHER']);
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $result = $this->engine->resolve($this->taxableLine($profile->id, '11800', [
        'included_tax_ids' => [$otherTax->id, $this->vat->id, fake()->uuid()],
    ]))->toArray();

    expect($result['base_amount'])->toBe('10000')
        ->and(collect($result['taxes'])->pluck('code')->all())->toBe(['VAT']);

    $onlyOthers = $this->engine->resolve($this->taxableLine($profile->id, '11800', ['included_tax_ids' => [$otherTax->id]]))->toArray();

    expect($onlyOthers)->toMatchArray(['base_amount' => '11800', 'included_tax_amount' => '0', 'added_tax_amount' => '2124']);
});

it('keeps the amount of a line without profile, whatever it claims to contain', function (): void {
    $result = $this->engine->resolve($this->taxableLine(null, '11800', ['included_tax_ids' => [$this->vat->id]]))->toArray();

    expect($result)->toMatchArray([
        'amount'           => '11800',
        'base_amount'      => '11800',
        'total_tax_amount' => '0',
        'total_amount'     => '11800',
    ]);
});

it('refuses an amount that contains a tax it cannot be taken out of', function (array $definition, string $messageKey): void {
    $jumpy = $this->createTax(['code' => 'JUMPY', ...$definition]);
    $profile = $this->createProfile([['tax_id' => $jumpy->id, 'treatment' => 'standard']]);

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '11800', ['included_tax_ids' => [$jumpy->id]])))
        ->toThrow(TaxException::class, __($messageKey, ['code' => 'JUMPY']));

    // on top of the amount, the same tax is calculated as usual
    expect($this->engine->resolve($this->taxableLine($profile->id, '11800'))->totalAmount)->not->toBe('11800');
})->with([
    'a rate applied to the whole amount past a threshold' => [
        ['is_progressive' => true, 'bracket_mode' => 'whole_amount', 'brackets' => [
            ['base_min' => '0', 'base_max' => '1000', 'value' => '10'],
            ['base_min' => '1000', 'base_max' => null, 'value' => '20'],
        ]],
        'tax::exceptions.included_tax_whole_amount',
    ],
    'a fixed amount changing by bracket' => [
        ['calculation_type' => 'fixed', 'fixed_mode' => 'per_line', 'is_progressive' => true, 'brackets' => [
            ['base_min' => '0', 'base_max' => '1000', 'value' => '100'],
            ['base_min' => '1000', 'base_max' => null, 'value' => '500'],
        ]],
        'tax::exceptions.included_tax_fixed_by_bracket',
    ],
    'a minimum amount' => [
        ['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '1', 'tax_amount_min' => '500']]],
        'tax::exceptions.included_tax_has_minimum',
    ],
]);

it('refuses a contained tax calculated on a tax that comes on top, or subtracting a tax', function (string $operator, array $includedCodes, string $messageKey): void {
    $eco = $this->createPercentageTax('2', ['code' => 'ECO']);
    $vatOnEco = $this->createPercentageTax('18', [
        'code'         => 'VAT-ECO',
        'dependencies' => [['depends_on_tax_id' => $eco->id, 'operator' => $operator]],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $eco->id, 'treatment' => 'standard'],
        ['tax_id' => $vatOnEco->id, 'treatment' => 'standard'],
    ]);
    $ids = ['ECO' => $eco->id, 'VAT-ECO' => $vatOnEco->id];

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '11800', [
        'included_tax_ids' => array_map(fn (string $code): string => $ids[$code], $includedCodes),
    ])))->toThrow(TaxException::class, __($messageKey, ['code' => 'VAT-ECO', 'dependency' => 'ECO']));
})->with([
    'calculated on a tax that comes on top' => ['add', ['VAT-ECO'], 'tax::exceptions.included_tax_depends_on_added_tax'],
    'subtracting a contained tax'           => ['subtract', ['ECO', 'VAT-ECO'], 'tax::exceptions.included_tax_subtracts_dependency'],
]);

it('refuses an amount lower than the fixed taxes it contains', function (): void {
    $eco = $this->createTax([
        'code'             => 'ECO',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_line',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '500']],
    ]);
    $profile = $this->createProfile([['tax_id' => $eco->id, 'treatment' => 'standard']]);

    expect(fn () => $this->engine->resolve($this->taxableLine($profile->id, '499', ['included_tax_ids' => [$eco->id]])))
        ->toThrow(TaxException::class, __('tax::exceptions.amount_below_included_taxes'));

    expect($this->engine->resolve($this->taxableLine($profile->id, '500', ['included_tax_ids' => [$eco->id]]))->baseAmount)->toBe('0');
});

it('resolves amounts with and without contained taxes together, with as many queries for many lines as for a few', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);
    $countQueries = function (int $lineCount) use ($profile): int {
        app()->forgetScopedInstances();
        cache()->flush();
        $lines = collect(range(1, $lineCount))->mapWithKeys(fn (int $index): array => [
            "line-{$index}" => $this->taxableLine($profile->id, (string) ($index * 1180), [
                'included_tax_ids' => $index % 2 === 0 ? [$this->vat->id] : [],
            ]),
        ]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $results = app(TaxInterface::class)->resolveMany($lines);
        DB::disableQueryLog();

        expect($results['line-1']->totalAmount)->toBe('1392')
            ->and($results['line-2']->baseAmount)->toBe('2000')
            ->and($results['line-2']->totalAmount)->toBe('2360');

        return count(DB::getQueryLog());
    };

    expect($countQueries(40))->toBe($countQueries(4));
});

/** A profile with the value-added tax and a tax per kilogram, for records measured by mass. */
function profileWithTaxPerKilogram(Tax $vat): TaxProfile
{
    $perKilogram = test()->createTax([
        'code'             => 'PER-KG',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);

    return test()->createProfile([
        ['tax_id' => $vat->id, 'treatment' => 'standard'],
        ['tax_id' => $perKilogram->id, 'treatment' => 'standard'],
    ], ['code' => 'BY-MASS']);
}

/**
 * @param  list<string>  $includedTaxIds
 * @return Collection<int, IncludedTaxAmountData>
 */
function amountContaining(array $includedTaxIds): Collection
{
    return collect([IncludedTaxAmountData::fromArray(['amount' => '11800', 'included_tax_ids' => $includedTaxIds, 'unit_code' => 'kg'])]);
}

it('checks an assignment and the taxes amounts contain with one read of the profile', function (): void {
    $profile = profileWithTaxPerKilogram($this->vat);
    $massGroupId = app(MasterInterface::class)->unit('kg')->group_id;

    DB::flushQueryLog();
    DB::enableQueryLog();
    DB::transaction(fn () => $this->engine->assertProfileAssignableWithIncludedTaxes($profile->id, collect([$massGroupId]), amountContaining([$this->vat->id])));
    DB::disableQueryLog();

    $profileReads = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "tax_profiles"'))
        ->count();

    expect($profileReads)->toBe(1);
});

it('refuses in one call what the assignment check and the included taxes check each refuse', function (): void {
    $profile = profileWithTaxPerKilogram($this->vat);
    $massGroupId = app(MasterInterface::class)->unit('kg')->group_id;
    $volumeGroupId = app(MasterInterface::class)->unit('l')->group_id;
    $outsideTax = $this->createPercentageTax('3', ['code' => 'OUTSIDE']);
    $check = fn (string $unitGroupId, Collection $amounts) => DB::transaction(
        fn () => $this->engine->assertProfileAssignableWithIncludedTaxes($profile->id, collect([$unitGroupId]), $amounts),
    );

    expect(fn () => $check($volumeGroupId, amountContaining([$this->vat->id])))
        ->toThrow(TaxException::class, __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']))
        ->and(fn () => $check($massGroupId, amountContaining([$outsideTax->id])))
        ->toThrow(TaxException::class, __('tax::exceptions.included_taxes_outside_profile', ['code' => 'BY-MASS']));

    // without amounts, only the assignment is checked
    $check($massGroupId, collect());
    expect(fn () => $check($volumeGroupId, collect()))->toThrow(TaxException::class);
});
