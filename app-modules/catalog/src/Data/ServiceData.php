<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class ServiceData
{
    /** @param MissingValue|Collection<int, ServiceDeliverableTemplateData> $deliverableTemplates */
    private function __construct(
        public MissingValue|string $name,
        public MissingValue|string|null $sku,
        public MissingValue|string $unitGroupId,
        public MissingValue|bool $isActive,
        public MissingValue|Collection $deliverableTemplates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $isActive = $read->get('is_active', default: false);
        $deliverableTemplates = $read->get('deliverable_templates');

        return new self(
            name: $read->get('name'),
            sku: $read->get('sku', default: null),
            unitGroupId: $read->get('unit_group_id'),
            isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
            deliverableTemplates: $deliverableTemplates instanceof MissingValue
                ? $deliverableTemplates
                : collect($deliverableTemplates)->map(ServiceDeliverableTemplateData::fromArray(...)),
        );
    }
}
