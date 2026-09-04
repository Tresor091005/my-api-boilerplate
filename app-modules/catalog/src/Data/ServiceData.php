<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Illuminate\Support\Collection;

final readonly class ServiceData
{
    /** @param Collection<int, array{name: string, position: int}> $templates */
    public function __construct(
        public string $name,
        public ?string $sku,
        public string $unitGroupId,
        public bool $isActive,
        public Collection $templates,
    ) {}
}
