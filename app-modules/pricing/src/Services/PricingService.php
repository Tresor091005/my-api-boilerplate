<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Support\PreciseConversion;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Pricing\Assertions\PricingAssertion;
use Lahatre\Pricing\Contracts\HasPriceable;
use Lahatre\Pricing\Contracts\HasPricingParty;
use Lahatre\Pricing\Contracts\PricingInterface;
use Lahatre\Pricing\Exceptions\Pricing\ChosenPriceAmountNotAllowedException;
use Lahatre\Pricing\Exceptions\Pricing\InvalidPriceableTargetException;
use Lahatre\Pricing\Exceptions\Pricing\InvalidPricingPartyTargetException;
use Lahatre\Pricing\Exceptions\Pricing\PriceEntryScopeConflictException;
use Lahatre\Pricing\Exceptions\Pricing\PriceUnitMismatchException;
use Lahatre\Pricing\Exceptions\Pricing\PricingBypassReasonRequiredException;
use Lahatre\Pricing\Models\PartyGroup;
use Lahatre\Pricing\Models\PartyGroupMember;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Pricing\Models\PriceableGroupMember;
use Lahatre\Pricing\Models\PriceEntry;
use Lahatre\Pricing\Support\PricingScopeData;
use Lahatre\Pricing\ViewData\PriceValidationResult;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class PricingService implements PricingInterface, StandaloneService
{
    private const UNIT_SCALE = 10;

    public function __construct(
        protected PricingAssertion $pricingAssertion,
        protected UnitCache $unitCache,
    ) {}

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
    ): PriceEntry {
        $unit = $this->unitCache->getByCode($unitCode);
        $currency = $this->unitCache->getCurrencyByCode($currencyCode);

        if ($priceableTarget instanceof Model) {
            $this->pricingAssertion->assertScopedModelBelongsToOrganization($priceableTarget, $organizationId, InvalidPriceableTargetException::class, [
                'priceable_type' => $priceableTarget->getMorphClass(),
                'priceable_id'   => (string) $priceableTarget->getKey(),
            ]);
        }

        if ($partyTarget instanceof Model) {
            $this->pricingAssertion->assertScopedModelBelongsToOrganization($partyTarget, $organizationId, InvalidPricingPartyTargetException::class, [
                'party_type' => $partyTarget->getMorphClass(),
                'party_id'   => (string) $partyTarget->getKey(),
            ]);
        }

        $this->assertPriceableTargetCompatibility($priceableTarget, $unit);

        $normalizedMinQuantity = $this->normalizeQuantityToBase($this->normalizeNumeric($minQuantity), $unit);

        $normalizedMaxQuantity = $maxQuantity !== null
            ? $this->normalizeQuantityToBase($this->normalizeNumeric($maxQuantity), $unit)
            : null;

        $this->pricingAssertion->assertValidRange($normalizedMinQuantity, $normalizedMaxQuantity, $startsAt, $endsAt);

        $scope = new PricingScopeData(
            organizationId: $organizationId,
            priceableType: $priceableTarget->getMorphClass(),
            priceableId: (string) $priceableTarget->getKey(),
            priceableKind: $priceableTarget instanceof HasPriceable ? 'item' : 'group',
            partyType: $partyTarget?->getMorphClass(),
            partyId: $partyTarget ? (string) $partyTarget->getKey() : null,
            partyKind: $partyTarget instanceof HasPricingParty ? 'actor' : ($partyTarget instanceof PartyGroup ? 'group' : null),
            context: $context,
            currencyCode: $currencyCode,
            unitCode: $unitCode,
            minQuantity: $normalizedMinQuantity,
            maxQuantity: $normalizedMaxQuantity,
            unitPrice: (int) PreciseConversion::toMinorUnits($this->normalizeNumeric($unitPrice), $currency),
            startsAt: $startsAt?->toDateTimeString(),
            endsAt: $endsAt?->toDateTimeString(),
            isActive: $isActive,
            metadata: $metadata,
        );

        if ($this->hasScopeConflict($scope)) {
            throw new PriceEntryScopeConflictException($scope->toScopeContext());
        }

        return DB::transaction(function () use ($scope): PriceEntry {
            return PriceEntry::query()->create([
                'organization_id' => $scope->organizationId,
                'priceable_type'  => $scope->priceableType,
                'priceable_id'    => $scope->priceableId,
                'priceable_kind'  => $scope->priceableKind,
                'party_type'      => $scope->partyType,
                'party_id'        => $scope->partyId,
                'party_kind'      => $scope->partyKind,
                'context'         => $scope->context,
                'currency_code'   => $scope->currencyCode,
                'unit_code'       => $scope->unitCode,
                'min_quantity'    => $scope->minQuantity,
                'max_quantity'    => $scope->maxQuantity,
                'unit_price'      => $scope->unitPrice,
                'starts_at'       => $scope->startsAt,
                'ends_at'         => $scope->endsAt,
                'is_active'       => $scope->isActive,
                'metadata'        => $scope->metadata,
            ]);
        });
    }

    public function resolveApplicablePrices(
        string $organizationId,
        HasPriceable $priceable,
        ?HasPricingParty $party,
        string $context,
        string $currencyCode,
        string $unitCode,
        string|int|float $quantity,
        ?CarbonImmutable $date = null,
    ): Collection {
        if ($priceable instanceof Model) {
            $this->pricingAssertion->assertScopedModelBelongsToOrganization($priceable, $organizationId, InvalidPriceableTargetException::class, [
                'priceable_type' => $priceable->getMorphClass(),
                'priceable_id'   => (string) $priceable->getKey(),
            ]);
        }

        if ($party instanceof Model) {
            $this->pricingAssertion->assertScopedModelBelongsToOrganization($party, $organizationId, InvalidPricingPartyTargetException::class, [
                'party_type' => $party->getMorphClass(),
                'party_id'   => (string) $party->getKey(),
            ]);
        }

        $requestUnit = $this->unitCache->getByCode($unitCode);
        if ($priceable->getPricingUnitGroupId() !== $requestUnit->group_id) {
            throw new PriceUnitMismatchException([
                'unit_code'              => $requestUnit->code,
                'expected_unit_group_id' => $priceable->getPricingUnitGroupId(),
                'actual_unit_group_id'   => $requestUnit->group_id,
                'priceable_type'         => $priceable->getMorphClass(),
                'priceable_id'           => (string) $priceable->getKey(),
            ]);
        }

        $baseQuantity = $this->normalizeQuantityToBase($this->normalizeNumeric($quantity), $requestUnit);

        $priceableGroupIds = $this->resolvePriceableGroupIds($organizationId, $priceable);
        $partyGroupIds = $party !== null
            ? $this->resolvePartyGroupIds($organizationId, $party)
            : collect();

        $query = PriceEntry::query()
            ->where('organization_id', $organizationId)
            ->where('context', $context)
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->where('unit_code', $unitCode)
            ->where('min_quantity', '<=', $baseQuantity)
            ->where(function (Builder $query) use ($baseQuantity): void {
                $query->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $baseQuantity);
            })
            ->where(function (Builder $query) use ($priceable, $priceableGroupIds): void {
                $query->where(function (Builder $query) use ($priceable): void {
                    $query->where('priceable_kind', 'item')
                        ->where('priceable_type', $priceable->getMorphClass())
                        ->where('priceable_id', (string) $priceable->getKey());
                });

                if ($priceableGroupIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $query) use ($priceableGroupIds): void {
                        $query->where('priceable_kind', 'group')
                            ->where('priceable_type', (new PriceableGroup())->getMorphClass())
                            ->whereIn('priceable_id', $priceableGroupIds->all());
                    });
                }
            })
            ->where(function (Builder $query) use ($party, $partyGroupIds): void {
                if ($party !== null) {
                    $query->where(function (Builder $query) use ($party): void {
                        $query->where('party_kind', 'actor')
                            ->where('party_type', $party->getMorphClass())
                            ->where('party_id', (string) $party->getKey());
                    });

                    if ($partyGroupIds->isNotEmpty()) {
                        $query->orWhere(function (Builder $query) use ($partyGroupIds): void {
                            $query->where('party_kind', 'group')
                                ->where('party_type', (new PartyGroup())->getMorphClass())
                                ->whereIn('party_id', $partyGroupIds->all());
                        });
                    }

                    $query->orWhere(function (Builder $query): void {
                        $query->whereNull('party_kind')
                            ->whereNull('party_type')
                            ->whereNull('party_id');
                    });

                    return;
                }

                $query->whereNull('party_kind')
                    ->whereNull('party_type')
                    ->whereNull('party_id');
            });

        if ($date !== null) {
            $query
                ->where(function (Builder $query) use ($date): void {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $date);
                })
                ->where(function (Builder $query) use ($date): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $date);
                });
        }

        return $query->get()
            ->sort(function (PriceEntry $left, PriceEntry $right): int {
                $comparisons = [
                    $this->priceableSpecificityRank($left) <=> $this->priceableSpecificityRank($right),
                    $this->partySpecificityRank($left) <=> $this->partySpecificityRank($right),
                    bccomp((string) $right->min_quantity, (string) $left->min_quantity, self::UNIT_SCALE),
                    ($right->starts_at?->getTimestamp() ?? PHP_INT_MIN) <=> ($left->starts_at?->getTimestamp() ?? PHP_INT_MIN),
                    ($right->created_at?->getTimestamp() ?? PHP_INT_MIN) <=> ($left->created_at?->getTimestamp() ?? PHP_INT_MIN),
                    $left->id <=> $right->id,
                ];

                foreach ($comparisons as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values();
    }

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
    ): PriceValidationResult {
        $applicablePrices = $this->resolveApplicablePrices(
            organizationId: $organizationId,
            priceable: $priceable,
            party: $party,
            context: $context,
            currencyCode: $currencyCode,
            unitCode: $unitCode,
            quantity: $quantity,
            date: $date,
        );

        $currency = $this->unitCache->getCurrencyByCode($currencyCode);
        $chosenAmount = $this->normalizeNumeric($unitPrice);

        $matchedPrices = $applicablePrices->filter(
            fn (PriceEntry $priceEntry): bool => PreciseConversion::toMinorUnits($chosenAmount, $currency) === (string) $priceEntry->unit_price
        )->values();

        if ($matchedPrices->isNotEmpty()) {
            return new PriceValidationResult(
                applicablePrices: $applicablePrices,
                matchedPrices: $matchedPrices,
            );
        }

        if (!$canBypass) {
            throw new ChosenPriceAmountNotAllowedException([
                'organization_id'  => $organizationId,
                'priceable_type'   => $priceable->getMorphClass(),
                'priceable_id'     => (string) $priceable->getKey(),
                'party_type'       => $party?->getMorphClass(),
                'party_id'         => $party ? (string) $party->getKey() : null,
                'context'          => $context,
                'currency_code'    => $currencyCode,
                'unit_code'        => $unitCode,
                'quantity'         => $quantity,
                'chosen_amount'    => $chosenAmount,
                'expected_amounts' => $applicablePrices->pluck('unit_price')->all(),
            ]);
        }

        if ($bypassReason === null || trim($bypassReason) === '') {
            throw new PricingBypassReasonRequiredException(['chosen_amount' => $chosenAmount]);
        }

        $expectedAmounts = $applicablePrices
            ->map(fn (PriceEntry $priceEntry): string => PreciseConversion::fromMinorUnits((string) $priceEntry->unit_price, $currency))
            ->values();

        return new PriceValidationResult(
            applicablePrices: $applicablePrices,
            matchedPrices: collect(),
            isBypassed: true,
            bypassAudit: [
                'pricing_bypassed'       => true,
                'pricing_bypass_reason'  => $bypassReason,
                'pricing_bypassed_by'    => $bypassedBy?->getMorphClass(),
                'pricing_bypassed_by_id' => $bypassedBy ? (string) $bypassedBy->getKey() : null,
                'pricing_bypassed_at'    => now()->toISOString(),
                'expected_amounts'       => $expectedAmounts->all(),
                'chosen_amount'          => $chosenAmount,
            ],
        );
    }

    public function syncPriceableGroupMembers(PriceableGroup $group, Collection $priceables): PriceableGroup
    {
        $group->loadMissing('members');

        $priceables = $priceables
            ->values()
            ->map(fn (mixed $priceable): HasPriceable => $this->pricingAssertion->assertValidPriceableModel($priceable, [
                'group_id' => $group->id,
            ]));

        foreach ($priceables as $priceable) {
            if ($priceable instanceof Model) {
                $this->pricingAssertion->assertScopedModelBelongsToOrganization($priceable, $group->organization_id, InvalidPriceableTargetException::class, [
                    'group_id' => $group->id,
                ]);
            }
        }

        if ($priceables->isNotEmpty()) {
            $this->pricingAssertion->assertUniformPriceableUnitGroup($priceables);
        }

        DB::transaction(function () use ($group, $priceables): void {
            $group->members()->delete();

            if ($priceables->isEmpty()) {
                return;
            }

            $now = now();

            PriceableGroupMember::query()->insert(
                $priceables
                    ->unique(fn (HasPriceable $priceable): string => $priceable->getMorphClass().':'.(string) $priceable->getKey())
                    ->map(fn (HasPriceable $priceable): array => [
                        'id'              => (string) Str::uuid7(),
                        'organization_id' => $group->organization_id,
                        'group_id'        => $group->id,
                        'priceable_type'  => $priceable->getMorphClass(),
                        'priceable_id'    => (string) $priceable->getKey(),
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ])
                    ->all()
            );
        });

        return $group->load('members');
    }

    public function syncPartyGroupMembers(PartyGroup $group, Collection $parties): PartyGroup
    {
        $parties = $parties
            ->values()
            ->map(fn (mixed $party): HasPricingParty => $this->pricingAssertion->assertValidPricingPartyModel($party, [
                'group_id' => $group->id,
            ]));

        foreach ($parties as $party) {
            if ($party instanceof Model) {
                $this->pricingAssertion->assertScopedModelBelongsToOrganization($party, $group->organization_id, InvalidPricingPartyTargetException::class, [
                    'group_id' => $group->id,
                ]);
            }
        }

        DB::transaction(function () use ($group, $parties): void {
            $group->members()->delete();

            if ($parties->isEmpty()) {
                return;
            }

            $now = now();

            PartyGroupMember::query()->insert(
                $parties
                    ->unique(fn (HasPricingParty $party): string => $party->getMorphClass().':'.(string) $party->getKey())
                    ->map(fn (HasPricingParty $party): array => [
                        'id'              => (string) Str::uuid7(),
                        'organization_id' => $group->organization_id,
                        'group_id'        => $group->id,
                        'party_type'      => $party->getMorphClass(),
                        'party_id'        => (string) $party->getKey(),
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ])
                    ->all()
            );
        });

        return $group->load('members');
    }

    protected function hasScopeConflict(PricingScopeData $scope): bool
    {
        if (!$scope->isActive) {
            return false;
        }

        return PriceEntry::query()
            ->where('organization_id', $scope->organizationId)
            ->where('priceable_type', $scope->priceableType)
            ->where('priceable_id', $scope->priceableId)
            ->where('priceable_kind', $scope->priceableKind)
            ->where('context', $scope->context)
            ->where('currency_code', $scope->currencyCode)
            ->where('unit_code', $scope->unitCode)
            ->where('min_quantity', $scope->minQuantity)
            ->where(function (Builder $query) use ($scope): void {
                if ($scope->maxQuantity === null) {
                    $query->whereNull('max_quantity');

                    return;
                }

                $query->where('max_quantity', $scope->maxQuantity);
            })
            ->where(function (Builder $query) use ($scope): void {
                if ($scope->partyType === null) {
                    $query->whereNull('party_type')
                        ->whereNull('party_id')
                        ->whereNull('party_kind');

                    return;
                }

                $query->where('party_type', $scope->partyType)
                    ->where('party_id', $scope->partyId)
                    ->where('party_kind', $scope->partyKind);
            })
            ->where(function (Builder $query) use ($scope): void {
                if ($scope->startsAt === null) {
                    $query->whereNull('starts_at');

                    return;
                }

                $query->where('starts_at', $scope->startsAt);
            })
            ->where(function (Builder $query) use ($scope): void {
                if ($scope->endsAt === null) {
                    $query->whereNull('ends_at');

                    return;
                }

                $query->where('ends_at', $scope->endsAt);
            })
            ->where('is_active', true)
            ->exists();
    }

    protected function assertPriceableTargetCompatibility(HasPriceable|PriceableGroup $target, Unit $unit): void
    {
        if ($target instanceof HasPriceable) {
            if ($target->getPricingUnitGroupId() !== $unit->group_id) {
                throw new PriceUnitMismatchException([
                    'unit_code'              => $unit->code,
                    'expected_unit_group_id' => $target->getPricingUnitGroupId(),
                    'actual_unit_group_id'   => $unit->group_id,
                    'priceable_type'         => $target->getMorphClass(),
                    'priceable_id'           => (string) $target->getKey(),
                ]);
            }

            return;
        }

        $members = $target->members()
            ->where('organization_id', $target->organization_id)
            ->with('priceable')
            ->get()
            ->map(fn (PriceableGroupMember $member): mixed => $member->priceable)
            ->filter(fn (mixed $priceable): bool => $priceable instanceof HasPriceable)
            ->values();

        if ($members->isEmpty()) {
            return;
        }

        $expectedGroupId = $this->pricingAssertion->assertUniformPriceableUnitGroup($members);

        if ($expectedGroupId !== $unit->group_id) {
            throw new PriceUnitMismatchException([
                'unit_code'              => $unit->code,
                'expected_unit_group_id' => $expectedGroupId,
                'actual_unit_group_id'   => $unit->group_id,
                'priceable_group_id'     => $target->id,
            ]);
        }
    }

    protected function assertItemUnitCompatibility(HasPriceable $priceable, Unit $unit): void
    {
        if ($priceable->getPricingUnitGroupId() !== $unit->group_id) {
            throw new PriceUnitMismatchException([
                'unit_code'              => $unit->code,
                'expected_unit_group_id' => $priceable->getPricingUnitGroupId(),
                'actual_unit_group_id'   => $unit->group_id,
                'priceable_type'         => $priceable->getMorphClass(),
                'priceable_id'           => (string) $priceable->getKey(),
            ]);
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected function resolvePriceableGroupIds(string $organizationId, HasPriceable $priceable): Collection
    {
        return PriceableGroupMember::query()
            ->where('organization_id', $organizationId)
            ->where('priceable_type', $priceable->getMorphClass())
            ->where('priceable_id', (string) $priceable->getKey())
            ->whereHas('group', function (Builder $query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->pluck('group_id')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function resolvePartyGroupIds(string $organizationId, HasPricingParty $party): Collection
    {
        return PartyGroupMember::query()
            ->where('organization_id', $organizationId)
            ->where('party_type', $party->getMorphClass())
            ->where('party_id', (string) $party->getKey())
            ->whereHas('group', function (Builder $query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true);
            })
            ->pluck('group_id')
            ->values();
    }

    protected function priceableSpecificityRank(PriceEntry $entry): int
    {
        return $entry->priceable_kind === 'item' ? 0 : 1;
    }

    protected function partySpecificityRank(PriceEntry $entry): int
    {
        return match ($entry->party_kind) {
            'actor' => 0,
            'group' => 1,
            default => 2,
        };
    }

    // TODO1: needs its own class
    protected function normalizeNumeric(string|int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $formatted = number_format($value, self::UNIT_SCALE, '.', '');

            return rtrim(rtrim($formatted, '0'), '.') ?: '0';
        }

        return trim($value);
    }

    protected function normalizeQuantityToBase(string $quantity, Unit $unit): string
    {
        $baseQuantity = $this->trimTrailingZeroes(
            PreciseConversion::convertUnitToBase($quantity, $unit)['amount']
        );

        if (str_contains($baseQuantity, '.')) {
            throw new InvalidPriceRangeException([
                'quantity'                 => $quantity,
                'unit_code'                => $unit->code,
                'normalized_base_quantity' => $baseQuantity,
                'reason'                   => 'base_quantity_must_be_integer',
            ]);
        }

        return $baseQuantity;
    }

    protected function trimTrailingZeroes(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
