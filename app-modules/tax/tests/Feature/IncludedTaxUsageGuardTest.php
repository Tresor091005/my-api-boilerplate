<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember();
    $this->eco = $this->createPercentageTax('2', ['code' => 'ECO']);
    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
});

/**
 * Pretends prices of items using every profile already contain the given sets
 * of taxes. The same source is kept for the whole test and only its sets
 * change: a controller keeps the services of its first request.
 *
 * @param  list<list<string>>  $includedTaxIdSets
 */
function pricesContain(array $includedTaxIdSets): void
{
    if (!app()->bound('tests.included-tax-usage')) {
        $usage = new class implements TaxProfileUsageInterface
        {
            /** @var list<list<string>> */
            public array $includedTaxIdSets = [];

            public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
            {
                return collect();
            }

            public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection
            {
                if ($this->includedTaxIdSets === []) {
                    return collect();
                }

                /** @var Collection<string, Collection<int, list<string>>> */
                return $taxProfileIds->mapWithKeys(fn (string $profileId): array => [$profileId => collect($this->includedTaxIdSets)]);
            }
        };
        app()->instance('tests.included-tax-usage', $usage);
        app()->instance(TaxProfileUsageInterface::class, $usage);
    }

    app('tests.included-tax-usage')->includedTaxIdSets = $includedTaxIdSets;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vatDefinition(array $overrides = []): array
{
    return [
        'code'             => 'VAT',
        'name'             => 'VAT',
        'kind'             => 'vat',
        'calculation_type' => 'percentage',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '18']],
        ...$overrides,
    ];
}

it('refuses to give a tax a shape no price can contain while prices contain it', function (array $change, string $reasonKey): void {
    $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']], ['code' => 'STANDARD']);
    pricesContain([[$this->vat->id]]);

    $this->putJson("/v1/tax/taxes/{$this->vat->id}", vatDefinition($change))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.included_taxes_in_use', [
            'profile' => 'STANDARD',
            'reason'  => __($reasonKey, ['code' => 'VAT']),
        ]))
        ->assertJsonPath('errors.context.tax_profile_code', 'STANDARD')
        ->assertJsonPath('errors.context.tax_code', 'VAT');

    expect(Tax::query()->findOrFail($this->vat->id)->is_progressive)->toBeFalse()
        ->and(Tax::query()->findOrFail($this->vat->id)->brackets()->value('tax_amount_min'))->toBeNull();

    // once no price contains the tax, the same change goes through
    pricesContain([]);
    $this->putJson("/v1/tax/taxes/{$this->vat->id}", vatDefinition($change))->assertNoContent();
})->with([
    'a minimum amount' => [
        ['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '50']]],
        'tax::exceptions.included_tax_has_minimum',
    ],
    'a rate applied to the whole amount past a threshold' => [
        ['is_progressive' => true, 'bracket_mode' => 'whole_amount', 'brackets' => [
            ['base_min' => '0', 'base_max' => '1000', 'value' => '10'],
            ['base_min' => '1000', 'base_max' => null, 'value' => '20'],
        ]],
        'tax::exceptions.included_tax_whole_amount',
    ],
]);

it('lets a tax prices contain change as long as a price can still contain it', function (array $change): void {
    $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);
    pricesContain([[$this->vat->id]]);

    $this->putJson("/v1/tax/taxes/{$this->vat->id}", vatDefinition($change))->assertNoContent();
})->with([
    'another rate'      => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '20']]]],
    'a ceiling'         => [['brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_max' => '5000']]]],
    'marginal brackets' => [['is_progressive' => true, 'bracket_mode' => 'marginal', 'brackets' => [
        ['base_min' => '0', 'base_max' => '1000', 'value' => '10'],
        ['base_min' => '1000', 'base_max' => null, 'value' => '20'],
    ]]],
    'another rounding' => [['rounding_mode' => 'up']],
]);

it('refuses to calculate a tax prices contain on a tax those prices leave out, or by subtracting one', function (string $operator, array $containedCodes, ?string $reasonKey): void {
    $this->createProfile([
        ['tax_id' => $this->eco->id, 'treatment' => 'standard'],
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
    ], ['code' => 'STANDARD']);
    $ids = ['ECO' => $this->eco->id, 'VAT' => $this->vat->id];
    pricesContain([array_map(fn (string $code): string => $ids[$code], $containedCodes)]);

    $response = $this->putJson("/v1/tax/taxes/{$this->vat->id}", vatDefinition([
        'dependencies' => [['depends_on_tax_id' => $this->eco->id, 'operator' => $operator]],
    ]));

    if ($reasonKey === null) {
        $response->assertNoContent();

        return;
    }

    $response->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.included_taxes_in_use', [
            'profile' => 'STANDARD',
            'reason'  => __($reasonKey, ['code' => 'VAT', 'dependency' => 'ECO']),
        ]));
})->with([
    'on a tax the prices leave out'           => ['add', ['VAT'], 'tax::exceptions.included_tax_depends_on_added_tax'],
    'on a tax the prices contain too'         => ['add', ['ECO', 'VAT'], null],
    'by subtracting a tax the prices contain' => ['subtract', ['ECO', 'VAT'], 'tax::exceptions.included_tax_subtracts_dependency'],
    'while the prices only contain the other' => ['add', ['ECO'], null],
]);

it('refuses a profile change that leaves the taxes prices contain without a base', function (): void {
    $vatOnEco = $this->createPercentageTax('18', [
        'code'         => 'VAT-ECO',
        'dependencies' => [['depends_on_tax_id' => $this->eco->id, 'operator' => 'add']],
    ]);
    // without the eco tax in the profile, the tax on it is calculated on the amount alone
    $profile = $this->createProfile([['tax_id' => $vatOnEco->id, 'treatment' => 'standard']], ['code' => 'STANDARD']);
    pricesContain([[$vatOnEco->id]]);
    $update = fn (array $lines) => $this->putJson("/v1/tax/profiles/{$profile->id}", ['code' => 'STANDARD', 'name' => 'Standard', 'lines' => $lines]);

    $update([['tax_id' => $vatOnEco->id, 'treatment' => 'standard'], ['tax_id' => $this->eco->id, 'treatment' => 'standard']])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.included_taxes_in_use', [
            'profile' => 'STANDARD',
            'reason'  => __('tax::exceptions.included_tax_depends_on_added_tax', ['code' => 'VAT-ECO', 'dependency' => 'ECO']),
        ]));

    $update([['tax_id' => $vatOnEco->id, 'treatment' => 'custom', 'brackets' => [
        ['base_min' => '0', 'base_max' => null, 'value' => '18', 'tax_amount_min' => '50'],
    ]]])
        ->assertUnprocessable()
        ->assertJsonPath('errors.context.tax_code', 'VAT-ECO');

    expect($profile->lines()->count())->toBe(1);

    // an exempt eco tax counts for nothing, and a tax that leaves the profile is no longer contained
    $update([['tax_id' => $vatOnEco->id, 'treatment' => 'standard'], ['tax_id' => $this->eco->id, 'treatment' => 'exempt']])->assertNoContent();
    $update([['tax_id' => $this->eco->id, 'treatment' => 'standard']])->assertNoContent();
});
