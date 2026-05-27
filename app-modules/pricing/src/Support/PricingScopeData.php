<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Support;

class PricingScopeData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $organizationId,
        public string $priceableType,
        public string $priceableId,
        public string $priceableKind,
        public ?string $partyType,
        public ?string $partyId,
        public ?string $partyKind,
        public string $context,
        public string $currencyCode,
        public string $unitCode,
        public string $minQuantity,
        public ?string $maxQuantity,
        public int $unitPrice,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $isActive,
        public ?array $metadata,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toScopeContext(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'priceable_type'  => $this->priceableType,
            'priceable_id'    => $this->priceableId,
            'priceable_kind'  => $this->priceableKind,
            'party_type'      => $this->partyType,
            'party_id'        => $this->partyId,
            'party_kind'      => $this->partyKind,
            'context'         => $this->context,
            'currency_code'   => $this->currencyCode,
            'unit_code'       => $this->unitCode,
            'min_quantity'    => $this->minQuantity,
            'max_quantity'    => $this->maxQuantity,
            'starts_at'       => $this->startsAt,
            'ends_at'         => $this->endsAt,
        ];
    }
}
