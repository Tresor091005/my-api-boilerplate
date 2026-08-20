<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\BillingException;

final readonly class FeatureDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public FeatureType $type,
        public ?string $resolverKey = null,
        public bool $isActive = true,
    ) {
        if ($this->type === FeatureType::Capacity && $this->resolverKey === null) {
            throw new BillingException("Capacity feature [{$this->key}] requires a resolver key.");
        }

        if ($this->type === FeatureType::Boolean && $this->resolverKey !== null) {
            throw new BillingException("Boolean feature [{$this->key}] cannot define a resolver key.");
        }
    }
}
