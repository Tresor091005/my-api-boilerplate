<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;
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

/**
 * Pretends records measured in the given unit groups use every profile, with
 * prices that already contain the given sets of taxes.
 *
 * @param  list<string>  $unitGroupIds
 * @param  list<list<string>>  $includedTaxIdSets
 */
function profilesUsedByUnitGroups(array $unitGroupIds, array $includedTaxIdSets = []): void
{
    app()->instance(TaxProfileUsageInterface::class, new class($unitGroupIds, $includedTaxIdSets) implements TaxProfileUsageInterface
    {
        /**
         * @param  list<string>  $unitGroupIds
         * @param  list<list<string>>  $includedTaxIdSets
         */
        public function __construct(private readonly array $unitGroupIds, private readonly array $includedTaxIdSets) {}

        public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            /** @var Collection<string, Collection<int, string>> */
            return $taxProfileIds->mapWithKeys(fn (string $profileId): array => [$profileId => collect($this->unitGroupIds)]);
        }

        public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            if ($this->includedTaxIdSets === []) {
                return collect();
            }

            /** @var Collection<string, Collection<int, list<string>>> */
            return $taxProfileIds->mapWithKeys(fn (string $profileId): array => [$profileId => collect($this->includedTaxIdSets)]);
        }
    });
}

it('manages a tax profile through the API', function (): void {
    $response = $this->postJson('/v1/tax/profiles?response=resource&include=lines.brackets,lines.tax(code)', [
        'code'  => 'dairy',
        'name'  => 'Dairy products',
        'lines' => [
            [
                'tax_id'    => $this->vat->id,
                'treatment' => 'custom',
                'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '5']],
            ],
            ['tax_id' => $this->eco->id, 'treatment' => 'exempt'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DAIRY')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonCount(2, 'data.lines');
    $profileId = $response->json('data.id');
    $lines = collect($response->json('data.lines'))->keyBy('tax.code');

    expect($lines['VAT']['treatment'])->toBe('custom')
        ->and($lines['VAT']['brackets'][0]['value'])->toBe('5.000000')
        ->and($lines['ECO']['brackets'])->toBe([])
        ->and($lines['ECO']['treatment'])->toBe('exempt');

    $this->getJson('/v1/tax/profiles')->assertOk()->assertJsonPath('data.0.id', $profileId);

    $this->putJson("/v1/tax/profiles/{$profileId}?response=resource&include=lines", [
        'code'  => 'DAIRY',
        'name'  => 'Dairy products',
        'lines' => [['tax_id' => $this->vat->id, 'treatment' => 'zero']],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.treatment', 'zero');

    $this->deleteJson("/v1/tax/profiles/{$profileId}")->assertNoContent();

    expect(TaxProfile::query()->find($profileId))->toBeNull();
});

it('refuses incoherent profile definitions', function (Closure $lines, string $messageKey): void {
    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => $lines($this)])
        ->assertUnprocessable()
        ->assertJsonPath('message', __($messageKey, ['code' => 'VAT', 'precision' => 0]));
})->with([
    'custom without a grid'        => [fn ($test): array => [['tax_id' => $test->vat->id, 'treatment' => 'custom']], 'tax::exceptions.custom_brackets_required'],
    'grid on a standard treatment' => [fn ($test): array => [[
        'tax_id'    => $test->vat->id,
        'treatment' => 'standard',
        'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '5']],
    ]], 'tax::exceptions.custom_brackets_not_allowed'],
    'several brackets for a non-progressive tax' => [fn ($test): array => [[
        'tax_id'    => $test->vat->id,
        'treatment' => 'custom',
        'brackets'  => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => null, 'value' => '5'],
        ],
    ]], 'tax::exceptions.single_open_bracket_required'],
    'custom grid with a gap' => [fn ($test): array => [[
        'tax_id' => $test->createPercentageTax('10', ['is_progressive' => true, 'bracket_mode' => 'marginal', 'brackets' => [
            ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
            ['base_min' => '10000', 'base_max' => null, 'value' => '10'],
        ]])->id,
        'treatment' => 'custom',
        'brackets'  => [
            ['base_min' => '0', 'base_max' => '5000', 'value' => '0'],
            ['base_min' => '6000', 'base_max' => null, 'value' => '5'],
        ],
    ]], 'tax::exceptions.brackets_not_contiguous'],
    'amount limits in the custom grid of a per-line tax' => [fn ($test): array => [[
        'tax_id'    => $test->createTax(['calculation_type' => 'fixed', 'fixed_mode' => 'per_line', 'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => '200']]])->id,
        'treatment' => 'custom',
        'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '100', 'tax_amount_min' => '50']],
    ]], 'tax::exceptions.bracket_amount_limits_not_allowed'],
    'custom grid finer than the currency' => [fn ($test): array => [[
        'tax_id'    => $test->vat->id,
        'treatment' => 'custom',
        'brackets'  => [['base_min' => '0', 'base_max' => null, 'value' => '5', 'tax_amount_min' => '0.5']],
    ]], 'tax::exceptions.monetary_precision_exceeded'],
    'two value-added taxes' => [fn ($test): array => [
        ['tax_id' => $test->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $test->createPercentageTax('10', ['kind' => 'vat'])->id, 'treatment' => 'standard'],
    ], 'tax::exceptions.multiple_vat_taxes'],
    'unknown tax'                       => [fn ($test): array => [['tax_id' => '0199f7a0-0000-7000-8000-000000000000', 'treatment' => 'standard']], 'tax::exceptions.profile_taxes_not_found'],
    'inactive tax in an active profile' => [fn ($test): array => [['tax_id' => $test->createPercentageTax('3', ['is_active' => false])->id, 'treatment' => 'standard']], 'tax::exceptions.profile_taxes_inactive'],
]);

it('requires the dependencies of a dependencies-only tax in the profile', function (): void {
    $cac = $this->createPercentageTax('10', [
        'code'         => 'CAC',
        'base_type'    => 'dependencies_only',
        'dependencies' => [['depends_on_tax_id' => $this->vat->id, 'operator' => 'add']],
    ]);

    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => [['tax_id' => $cac->id, 'treatment' => 'standard']]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.dependencies_missing_from_profile', ['code' => 'CAC']))
        ->assertJsonPath('errors.context.missing_dependency_codes', ['VAT']);
});

it('keeps a profile assigned to records active and compatible with their units', function (): void {
    $perKg = $this->createTax([
        'code'             => 'PER-KG',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'kg',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '100']],
    ]);
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']], ['code' => 'USED']);
    profilesUsedByUnitGroups([app(MasterInterface::class)->unit('l')->group_id]);

    $this->putJson("/v1/tax/profiles/{$profile->id}", [
        'code'      => 'USED',
        'name'      => 'Used',
        'is_active' => false,
        'lines'     => [['tax_id' => $this->vat->id, 'treatment' => 'standard']],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_in_use', ['code' => 'USED']));

    $this->putJson("/v1/tax/profiles/{$profile->id}", [
        'code'  => 'USED',
        'name'  => 'Used',
        'lines' => [['tax_id' => $this->vat->id, 'treatment' => 'standard'], ['tax_id' => $perKg->id, 'treatment' => 'standard']],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.tax_unit_incompatible', ['code' => 'PER-KG']));

    $this->deleteJson("/v1/tax/profiles/{$profile->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_in_use', ['code' => 'USED']));

    expect(TaxProfileLine::query()->where('tax_profile_id', $profile->id)->count())->toBe(1);
});

it('hides the profiles of another organization', function (): void {
    $foreignProfile = TaxProfile::factory()->create();

    $this->getJson("/v1/tax/profiles/{$foreignProfile->id}")->assertForbidden();
});

it('refuses a profile listing the same tax twice', function (): void {
    $this->postJson('/v1/tax/profiles', [
        'code'  => 'TWICE',
        'name'  => 'Twice',
        'lines' => [
            ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
            ['tax_id' => $this->vat->id, 'treatment' => 'exempt'],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.1.tax_id']);

    expect(TaxProfile::query()->where('code', 'TWICE')->exists())->toBeFalse();
});

it('refuses taxes of another organization in a profile', function (): void {
    $foreignTax = Tax::factory()->create();

    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => [['tax_id' => $foreignTax->id, 'treatment' => 'standard']]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_taxes_not_found'))
        ->assertJsonPath('errors.context.tax_ids', [$foreignTax->id]);

    expect(TaxProfile::query()->where('organization_id', $this->organization->id)->exists())->toBeFalse();
});

it('refuses to change or delete a profile of another organization', function (): void {
    $foreignProfile = TaxProfile::factory()->create(['code' => 'FOREIGN', 'name' => 'Foreign']);

    $this->putJson("/v1/tax/profiles/{$foreignProfile->id}", [
        'code'  => 'STOLEN',
        'name'  => 'Stolen',
        'lines' => [['tax_id' => $this->vat->id, 'treatment' => 'standard']],
    ])->assertForbidden();
    $this->deleteJson("/v1/tax/profiles/{$foreignProfile->id}")->assertForbidden();

    $foreignProfile->refresh();

    expect($foreignProfile->code)->toBe('FOREIGN')
        ->and($foreignProfile->name)->toBe('Foreign')
        ->and($foreignProfile->deleted_at)->toBeNull()
        ->and(TaxProfileLine::query()->where('tax_profile_id', $foreignProfile->id)->pluck('tax_id')->all())->not->toContain($this->vat->id);
});

it('requires the tax profile permissions to change a profile', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']], ['code' => 'KEPT']);
    $this->authenticateTaxMember(array_values(array_filter(
        $this->taxPermissions(),
        fn (string $permission): bool => !str_starts_with($permission, 'tax_profile.'),
    )));
    $payload = ['code' => 'KEPT', 'name' => 'Kept', 'lines' => [['tax_id' => $this->vat->id, 'treatment' => 'zero']]];

    $this->postJson('/v1/tax/profiles', [...$payload, 'code' => 'NEW'])->assertForbidden();
    $this->putJson("/v1/tax/profiles/{$profile->id}", $payload)->assertForbidden();
    $this->deleteJson("/v1/tax/profiles/{$profile->id}")->assertForbidden();

    expect(TaxProfile::query()->whereKey($profile->id)->exists())->toBeTrue()
        ->and(TaxProfileLine::query()->where('tax_profile_id', $profile->id)->firstOrFail()->treatment)->toBe(TaxTreatment::Standard);
});
