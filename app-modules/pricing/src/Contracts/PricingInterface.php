<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lahatre\Pricing\Models\PartyGroup;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Pricing\Models\PriceEntry;
use Lahatre\Pricing\ViewData\PriceValidationResult;

interface PricingInterface
{
    public function createPriceEntry(
        string $organizationId,
        HasPriceable|PriceableGroup $priceableTarget,
        string $context,
        string $currencyCode,
        string $unitCode,
        string|int|float $unitPrice,
        string|int|float $minQuantity = 0,
        string|int|float|null $maxQuantity = null,
        HasPricingParty|PartyGroup|null $partyTarget = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        bool $isActive = true,
        ?array $metadata = null,
    ): PriceEntry;

    // TODO: both methods below should support bulk operations later if it proves useful.
    /**
     * @return Collection<int, PriceEntry>
     */
    public function resolveApplicablePrices(
        string $organizationId,
        HasPriceable $priceable,
        ?HasPricingParty $party,
        string $context,
        string $currencyCode,
        string $unitCode,
        string|int|float $quantity,
        ?CarbonImmutable $date = null,
    ): Collection;

    public function validateChosenAmount(
        string|int|float $unitPrice,
        string $organizationId,
        HasPriceable $priceable,
        ?HasPricingParty $party,
        string $context,
        string $currencyCode,
        string $unitCode,
        string|int|float $quantity,
        ?CarbonImmutable $date = null,
        bool $canBypass = false,
        ?string $bypassReason = null,
        ?Model $bypassedBy = null,
    ): PriceValidationResult;

    /**
     * @param  Collection<int, HasPriceable>  $priceables
     */
    public function syncPriceableGroupMembers(PriceableGroup $group, Collection $priceables): PriceableGroup;

    /**
     * @param  Collection<int, HasPricingParty>  $parties
     */
    public function syncPartyGroupMembers(PartyGroup $group, Collection $parties): PartyGroup;
}
