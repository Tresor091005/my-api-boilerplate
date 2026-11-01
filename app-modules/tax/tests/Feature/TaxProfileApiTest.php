<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Exceptions\TaxException;
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

/** Pretends records measured in the given unit groups use every profile. */
function profilesUsedByUnitGroups(array $unitGroupIds): void
{
    app()->instance(TaxProfileUsageInterface::class, new class($unitGroupIds) implements TaxProfileUsageInterface
    {
        /** @param list<string> $unitGroupIds */
        public function __construct(private readonly array $unitGroupIds) {}

        public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
        {
            /** @var Collection<string, Collection<int, string>> */
            return $taxProfileIds->mapWithKeys(fn (string $profileId): array => [$profileId => collect($this->unitGroupIds)]);
        }
    });
}

it('manages a tax profile through the API', function (): void {
    $response = $this->postJson('/v1/tax/profiles?response=resource&include=lines.bracket_overrides,lines.tax(code)', [
        'code'  => 'dairy',
        'name'  => 'Dairy products',
        'lines' => [
            [
                'tax_id'            => $this->vat->id,
                'treatment'         => 'custom',
                'bracket_overrides' => [['tax_bracket_id' => $this->vat->brackets()->firstOrFail()->id, 'value' => '5']],
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
        ->and($lines['VAT']['bracket_overrides'][0]['value'])->toBe('5.000000')
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

it('simulates the taxes of a profile', function (): void {
    $vat = $this->createPercentageTax('18', [
        'code'         => 'VAT-ECO',
        'dependencies' => [['depends_on_tax_id' => $this->eco->id, 'operator' => 'add']],
    ]);
    $profile = $this->createProfile([
        ['tax_id' => $vat->id, 'treatment' => 'standard'],
        ['tax_id' => $this->eco->id, 'treatment' => 'standard'],
    ]);

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '10000', 'quantity' => '2'])
        ->assertOk()
        ->assertJsonPath('data.schema_version', 1)
        ->assertJsonPath('data.currency_code', 'XOF')
        ->assertJsonPath('data.quantity', '2')
        ->assertJsonPath('data.total_tax_amount', '2390')
        ->assertJsonPath('data.taxes.1.code', 'VAT-ECO')
        ->assertJsonPath('data.taxes.1.taxable_base_amount', '10500');

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '10.5', 'quantity' => '0'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['base_amount', 'quantity']);
});

it('refuses incoherent profile definitions', function (Closure $lines, string $messageKey): void {
    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => $lines($this)])
        ->assertUnprocessable()
        ->assertJsonPath('message', __($messageKey, ['code' => 'VAT', 'precision' => 0]));
})->with([
    'custom without override'          => [fn ($test): array => [['tax_id' => $test->vat->id, 'treatment' => 'custom']], 'tax::exceptions.bracket_override_required'],
    'override on a standard treatment' => [fn ($test): array => [[
        'tax_id'            => $test->vat->id,
        'treatment'         => 'standard',
        'bracket_overrides' => [['tax_bracket_id' => $test->vat->brackets()->firstOrFail()->id, 'value' => '5']],
    ]], 'tax::exceptions.bracket_override_not_allowed'],
    'override of another tax bracket' => [fn ($test): array => [[
        'tax_id'            => $test->vat->id,
        'treatment'         => 'custom',
        'bracket_overrides' => [['tax_bracket_id' => $test->eco->brackets()->firstOrFail()->id, 'value' => '5']],
    ]], 'tax::exceptions.bracket_not_owned_by_tax'],
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
    $this->postJson("/v1/tax/profiles/{$foreignProfile->id}/calculations", ['base_amount' => '100', 'quantity' => '1'])->assertForbidden();
});

it('previews a draft profile that real resolution still refuses', function (): void {
    $draftTax = $this->createPercentageTax('10', ['code' => 'DRAFT-TAX', 'is_active' => false]);
    $profile = $this->createProfile([['tax_id' => $draftTax->id, 'treatment' => 'standard']], ['code' => 'DRAFT', 'is_active' => false]);

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '10000', 'quantity' => '1'])
        ->assertOk()
        ->assertJsonPath('data.tax_profile_id', $profile->id)
        ->assertJsonPath('data.taxes.0.code', 'DRAFT-TAX')
        ->assertJsonPath('data.total_tax_amount', '1000');

    expect(fn () => app(TaxInterface::class)->resolve($this->taxableLine($profile->id, '10000')))
        ->toThrow(TaxException::class, __('tax::exceptions.profile_inactive', ['code' => 'DRAFT']));
});

it('refuses invalid amounts and quantities in a preview', function (array $payload, string $errorPath): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']]);

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '10000', 'quantity' => '1', ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$errorPath]);
})->with([
    'negative base amount'   => [['base_amount' => '-100'], 'base_amount'],
    'fractional base amount' => [['base_amount' => '100.5'], 'base_amount'],
    'zero quantity'          => [['quantity' => '0'], 'quantity'],
    'zero decimal quantity'  => [['quantity' => '0.000'], 'quantity'],
]);

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

it('refuses taxes and brackets of another organization in a profile', function (): void {
    $foreignTax = Tax::factory()->create();
    $foreignBracketId = $foreignTax->brackets()->firstOrFail()->id;

    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => [['tax_id' => $foreignTax->id, 'treatment' => 'standard']]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.profile_taxes_not_found'))
        ->assertJsonPath('errors.context.tax_ids', [$foreignTax->id]);

    $this->postJson('/v1/tax/profiles', ['code' => 'P', 'name' => 'Profile', 'lines' => [[
        'tax_id'            => $this->vat->id,
        'treatment'         => 'custom',
        'bracket_overrides' => [['tax_bracket_id' => $foreignBracketId, 'value' => '5']],
    ]]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('tax::exceptions.bracket_not_owned_by_tax'))
        ->assertJsonPath('errors.context.tax_bracket_id', $foreignBracketId);

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

it('requires the tax profile permissions to change or preview a profile', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->vat->id, 'treatment' => 'standard']], ['code' => 'KEPT']);
    $this->authenticateTaxMember(array_values(array_filter(
        $this->taxPermissions(),
        fn (string $permission): bool => !str_starts_with($permission, 'tax_profile.'),
    )));
    $payload = ['code' => 'KEPT', 'name' => 'Kept', 'lines' => [['tax_id' => $this->vat->id, 'treatment' => 'zero']]];

    $this->postJson('/v1/tax/profiles', [...$payload, 'code' => 'NEW'])->assertForbidden();
    $this->putJson("/v1/tax/profiles/{$profile->id}", $payload)->assertForbidden();
    $this->deleteJson("/v1/tax/profiles/{$profile->id}")->assertForbidden();
    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '100', 'quantity' => '1'])->assertForbidden();

    expect(TaxProfile::query()->whereKey($profile->id)->exists())->toBeTrue()
        ->and(TaxProfileLine::query()->where('tax_profile_id', $profile->id)->firstOrFail()->treatment)->toBe(TaxTreatment::Standard);
});

it('previews a per-unit tax on the sold quantity converted into the tax unit', function (): void {
    $perPack = $this->createTax([
        'code'             => 'PER-PACK',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'b-6',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '300']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perPack->id, 'treatment' => 'standard']]);

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '1000', 'quantity' => '6', 'unit_code' => 'b'])
        ->assertOk()
        ->assertJsonPath('data.unit_code', 'b')
        ->assertJsonPath('data.taxes.0.code', 'PER-PACK')
        ->assertJsonPath('data.taxes.0.tax_quantity', '1')
        ->assertJsonPath('data.taxes.0.tax_unit_code', 'b-6')
        ->assertJsonPath('data.taxes.0.tax_amount', '300')
        ->assertJsonPath('data.total_tax_amount', '300');
});

it('refuses a per-unit preview without a compatible sale unit', function (array $payload, string $messageKey): void {
    $perPack = $this->createTax([
        'code'             => 'PER-PACK',
        'calculation_type' => 'fixed',
        'fixed_mode'       => 'per_tax_unit',
        'tax_unit_code'    => 'b-6',
        'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '300']],
    ]);
    $profile = $this->createProfile([['tax_id' => $perPack->id, 'treatment' => 'standard']]);

    $this->postJson("/v1/tax/profiles/{$profile->id}/calculations", ['base_amount' => '1000', 'quantity' => '6', ...$payload])
        ->assertUnprocessable()
        ->assertJsonPath('message', __($messageKey, ['code' => 'PER-PACK']))
        ->assertJsonPath('errors.context.tax_code', 'PER-PACK');
})->with([
    'sale unit of another unit group' => [['unit_code' => 'kg'], 'tax::exceptions.tax_unit_incompatible'],
    'sale unit missing'               => [[], 'tax::exceptions.sale_unit_required'],
]);
