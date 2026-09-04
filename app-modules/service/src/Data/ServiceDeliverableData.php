<?php

declare(strict_types=1);

namespace Lahatre\Service\Data;

use Lahatre\Shared\Data\MissingValue;

final readonly class ServiceDeliverableData
{
    public function __construct(
        public string|MissingValue $name,
        public int|MissingValue $position,
    ) {}
}
