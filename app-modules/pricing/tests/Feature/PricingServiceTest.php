<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Pricing\Exceptions\Pricing\ChosenPriceAmountNotAllowedException;
use Lahatre\Pricing\Exceptions\Pricing\PriceableGroupUnitMismatchException;
use Lahatre\Pricing\Exceptions\Pricing\PriceEntryScopeConflictException;
use Lahatre\Pricing\Exceptions\Pricing\PricingBypassReasonRequiredException;
use Lahatre\Pricing\Models\PartyGroup;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Pricing\Services\PricingService;
use Lahatre\Pricing\Tests\Concerns\InteractsWithPricingTestFixtures;
use Lahatre\Pricing\Tests\Concerns\InteractsWithPricingTenantContext;

uses(RefreshDatabase::class, InteractsWithPricingTenantContext::class, InteractsWithPricingTestFixtures::class);

beforeEach(function (): void {
    $this->initializePricingTenantContext();
    $this->ensurePricingTestTables();

    $this->service = app(PricingService::class);

    $suffix = Str::lower(Str::random(6));

    $this->currency = Currency::factory()->create([
        'precision' => 0,
    ]);

    $this->unitGroup = UnitGroup::factory()->create([
        'organization_id' => null,
        'name'            => "Weight {$suffix}",
    ]);

    $this->gram = Unit::factory()->create([
        'organization_id' => null,
        'code'            => "g_{$suffix}",
        'ratio'           => 1,
        'group_id'        => $this->unitGroup->id,
    ]);

    $this->kilogram = Unit::factory()->create([
        'organization_id' => null,
        'code'            => "kg_{$suffix}",
        'ratio'           => 1000,
        'group_id'        => $this->unitGroup->id,
    ]);

    $this->variant = $this->createTestPriceable([
        'organization_id' => $this->organizationId,
        'unit_group_id'   => $this->unitGroup->id,
    ]);

    $this->customer = $this->createTestParty([
        'organization_id' => $this->organizationId,
    ]);
    $this->otherCustomer = $this->createTestParty([
        'organization_id' => $this->otherOrganizationId,
    ]);
});

it('resolves prices from most specific to least specific', function (): void {
    $priceableGroup = PriceableGroup::factory()->create(['organization_id' => $this->organizationId]);
    $partyGroup = PartyGroup::factory()->create(['organization_id' => $this->organizationId]);

    $this->service->syncPriceableGroupMembers($priceableGroup, collect([$this->variant]));
    $this->service->syncPartyGroupMembers($partyGroup, collect([$this->customer]));

    $publicItem = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    $itemPartyGroup = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 900,
        minQuantity: 1,
        partyTarget: $partyGroup,
    );

    $itemActor = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 850,
        minQuantity: 1,
        partyTarget: $this->customer,
    );

    $groupPublic = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $priceableGroup,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 950,
        minQuantity: 1,
    );

    $resolved = $this->service->resolveApplicablePrices(
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: $this->customer,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 80,
        date: CarbonImmutable::now(),
    );

    expect($resolved->pluck('id')->all())->toBe([
        $itemActor->id,
        $itemPartyGroup->id,
        $publicItem->id,
        $groupPublic->id,
    ]);
});

it('prefers the highest minimum quantity inside the same specificity tier', function (): void {
    $lowTier = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    $highTier = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 900,
        minQuantity: 50,
    );

    $resolved = $this->service->resolveApplicablePrices(
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: null,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 80,
    );

    expect($resolved->pluck('id')->all())->toBe([$highTier->id, $lowTier->id]);
});

it('does not resolve prices from another compatible unit when no explicit unit price exists', function (): void {
    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    $resolved = $this->service->resolveApplicablePrices(
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: null,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->gram->code,
        quantity: 1500,
    );

    expect($resolved)->toBeEmpty();
});

it('accepts a non-best amount when it is still applicable', function (): void {
    $partyGroup = PartyGroup::factory()->create(['organization_id' => $this->organizationId]);
    $this->service->syncPartyGroupMembers($partyGroup, collect([$this->customer]));

    $publicItem = $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 850,
        minQuantity: 1,
        partyTarget: $partyGroup,
    );

    $result = $this->service->validateChosenAmount(
        unitPrice: 1000,
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: $this->customer,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 80,
    );

    expect($result->isMatched())->toBeTrue()
        ->and($result->isBypassed)->toBeFalse()
        ->and($result->matchedPrices->first()->id)->toBe($publicItem->id);
});

it('requires a bypass reason when a non-applicable amount is forced', function (): void {
    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    expect(fn () => $this->service->validateChosenAmount(
        unitPrice: 700,
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: null,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 5,
        canBypass: true,
    ))->toThrow(PricingBypassReasonRequiredException::class);
});

it('returns bypass audit metadata when a justified override is used', function (): void {
    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    $result = $this->service->validateChosenAmount(
        unitPrice: 700,
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: null,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 5,
        canBypass: true,
        bypassReason: 'Manual contract override',
        bypassedBy: $this->customer,
    );

    expect($result->isBypassed)->toBeTrue()
        ->and($result->bypassAudit)->not->toBeNull()
        ->and($result->bypassAudit['pricing_bypass_reason'])->toBe('Manual contract override')
        ->and($result->bypassAudit['chosen_amount'])->toBe('700')
        ->and($result->bypassAudit['expected_amounts'])->toBe(['1000']);
});

it('rejects a non-applicable amount when bypass is not allowed', function (): void {
    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    expect(fn () => $this->service->validateChosenAmount(
        unitPrice: 700,
        organizationId: $this->organizationId,
        priceable: $this->variant,
        party: null,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        quantity: 5,
    ))->toThrow(ChosenPriceAmountNotAllowedException::class);
});

it('prevents duplicate active scopes even when the unit price changes', function (): void {
    $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 1000,
        minQuantity: 1,
    );

    expect(fn () => $this->service->createPriceEntry(
        organizationId: $this->organizationId,
        priceableTarget: $this->variant,
        context: 'selling',
        currencyCode: $this->currency->code,
        unitCode: $this->kilogram->code,
        unitPrice: 900,
        minQuantity: 1,
    ))->toThrow(PriceEntryScopeConflictException::class);
});

it('rejects mixed unit groups inside one priceable group', function (): void {
    $otherUnitGroup = UnitGroup::factory()->create([
        'organization_id' => null,
        'name'            => 'Piece '.Str::lower(Str::random(6)),
    ]);

    Unit::factory()->create([
        'organization_id' => null,
        'code'            => 'unit_'.Str::lower(Str::random(6)),
        'ratio'           => 1,
        'group_id'        => $otherUnitGroup->id,
    ]);

    $otherVariant = $this->createTestPriceable([
        'organization_id' => $this->organizationId,
        'unit_group_id'   => $otherUnitGroup->id,
    ]);

    $group = PriceableGroup::factory()->create(['organization_id' => $this->organizationId]);

    expect(fn () => $this->service->syncPriceableGroupMembers($group, collect([
        $this->variant,
        $otherVariant,
    ])))->toThrow(PriceableGroupUnitMismatchException::class);
});
