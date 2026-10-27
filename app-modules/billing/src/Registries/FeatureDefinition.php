<?php

declare(strict_types=1);

namespace Lahatre\Billing\Registries;

use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\FeatureException;

final readonly class FeatureDefinition
{
    /**
     * @param  array{0: class-string, 1: string}|null  $counter
     *
     * @throws FeatureException when the key does not have the form module.feature
     */
    private function __construct(
        public string $key,
        public ?FeatureType $type,
        public ?array $counter,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw FeatureException::invalidKey($key);
        }
    }

    /**
     * @throws FeatureException when the key does not have the form module.feature
     */
    public static function boolean(string $key): self
    {
        return new self($key, FeatureType::Boolean, null);
    }

    /**
     * @param  array{0: class-string, 1: string}  $counter
     *
     * @throws FeatureException when the key does not have the form module.feature or the counter method does not exist
     */
    public static function capacity(string $key, array $counter): self
    {
        [$class, $method] = $counter;

        if (!method_exists($class, $method)) {
            throw FeatureException::counterMissing($key, $class, $method);
        }

        return new self($key, FeatureType::Capacity, [$class, $method]);
    }

    /**
     * @throws FeatureException when the key does not have the form module.feature
     */
    public static function disabled(string $key): self
    {
        return new self($key, null, null);
    }

    public function isDisabled(): bool
    {
        return $this->type === null;
    }

    public function translationKey(): string
    {
        [$module, $feature] = explode('.', $this->key, 2);

        return "{$module}::features.{$feature}";
    }
}
