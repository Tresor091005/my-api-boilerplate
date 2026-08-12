<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Assertions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lahatre\Pricing\Contracts\HasPriceable;
use Lahatre\Pricing\Contracts\HasPricingParty;
use Lahatre\Pricing\Exceptions\Pricing\InvalidPriceableTargetException;
use Lahatre\Pricing\Exceptions\Pricing\InvalidPriceRangeException;
use Lahatre\Pricing\Exceptions\Pricing\InvalidPricingPartyTargetException;
use Lahatre\Pricing\Exceptions\Pricing\PriceableGroupUnitMismatchException;

class PricingAssertion
{
    /**
     * @throws InvalidPriceRangeException
     */
    public function assertValidRange(string $minQuantity, ?string $maxQuantity, ?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): void
    {
        if (bccomp($minQuantity, '0', 10) === -1) {
            throw new InvalidPriceRangeException([
                'min_quantity' => $minQuantity,
            ]);
        }

        if ($maxQuantity !== null && bccomp($maxQuantity, $minQuantity, 10) === -1) {
            throw new InvalidPriceRangeException([
                'min_quantity' => $minQuantity,
                'max_quantity' => $maxQuantity,
            ]);
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt->lt($startsAt)) {
            throw new InvalidPriceRangeException([
                'starts_at' => $startsAt->toISOString(),
                'ends_at'   => $endsAt->toISOString(),
            ]);
        }
    }

    /**
     * @throws InvalidPriceableTargetException
     */
    public function assertValidPriceableModel(mixed $model, array $context = []): HasPriceable
    {
        if (!$model instanceof HasPriceable) {
            throw new InvalidPriceableTargetException($context);
        }

        return $model;
    }

    /**
     * @throws InvalidPricingPartyTargetException
     */
    public function assertValidPricingPartyModel(mixed $model, array $context = []): HasPricingParty
    {
        if (!$model instanceof HasPricingParty) {
            throw new InvalidPricingPartyTargetException($context);
        }

        return $model;
    }

    /**
     * @template TPriceable of HasPriceable
     *
     * @param  Collection<int, TPriceable>  $priceables
     *
     * @throws PriceableGroupUnitMismatchException
     */
    public function assertUniformPriceableUnitGroup(Collection $priceables): string
    {
        $unitGroupIds = $priceables
            ->map(fn (HasPriceable $priceable): string => $priceable->getPricingUnitGroupId())
            ->unique()
            ->values();

        if ($unitGroupIds->count() !== 1) {
            throw new PriceableGroupUnitMismatchException([
                'unit_group_ids' => $unitGroupIds->all(),
            ]);
        }

        return (string) $unitGroupIds->first();
    }

    public function assertScopedModelBelongsToOrganization(
        Model $model,
        string $organizationId,
        string $exceptionClass,
        array $context,
    ): void {
        if (!array_key_exists('organization_id', $model->getAttributes())) {
            throw new $exceptionClass(array_merge($context, [
                'expected_organization_id' => $organizationId,
                'actual_organization_id'   => null,
                'reason'                   => 'missing_organization_id_attribute',
            ]));
        }

        $modelOrganizationId = $model->getAttribute('organization_id');

        if ($modelOrganizationId === null || $modelOrganizationId !== $organizationId) {
            throw new $exceptionClass(array_merge($context, [
                'expected_organization_id' => $organizationId,
                'actual_organization_id'   => $modelOrganizationId,
            ]));
        }
    }
}
