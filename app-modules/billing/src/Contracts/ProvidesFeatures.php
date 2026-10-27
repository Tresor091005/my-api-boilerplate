<?php

declare(strict_types=1);

namespace Lahatre\Billing\Contracts;

use Lahatre\Billing\Registries\FeatureDefinition;

interface ProvidesFeatures
{
    /** @return list<FeatureDefinition> */
    public function definitions(): array;
}
