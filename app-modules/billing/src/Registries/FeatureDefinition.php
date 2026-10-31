<?php

declare(strict_types=1);

namespace Lahatre\Billing\Registries;

use Carbon\CarbonInterval;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\FeatureException;

final readonly class FeatureDefinition
{
    /**
     * @param  array{0: class-string, 1: string}|null  $counter
     * @param  list<string>|null  $options
     *
     * @throws FeatureException when the key does not have the form module.feature
     */
    private function __construct(
        public string $key,
        public ?FeatureType $type,
        public ?array $counter = null,
        public ?CarbonInterval $window = null,
        public ?array $options = null,
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
        return new self($key, FeatureType::Boolean);
    }

    /**
     * The counter receives the organization and, with a window, the moment the
     * window starts: it counts what was used since then, so the allowance comes
     * back as time passes.
     *
     * @param  array{0: class-string, 1: string}  $counter
     *
     * @throws FeatureException when the key does not have the form module.feature or the counter method does not exist
     */
    public static function capacity(string $key, array $counter, ?CarbonInterval $window = null): self
    {
        [$class, $method] = $counter;

        if (!method_exists($class, $method)) {
            throw FeatureException::counterMissing($key, $class, $method);
        }

        return new self($key, FeatureType::Capacity, [$class, $method], $window);
    }

    /**
     * A plan grants some of these values, such as the kinds of file it may store.
     *
     * @param  list<string>  $options
     *
     * @throws FeatureException when the key or an option is malformed
     */
    public static function choice(string $key, array $options): self
    {
        if ($options === [] || array_unique($options) !== $options || preg_grep('/^[a-z][a-z0-9_]*$/', $options, PREG_GREP_INVERT) !== []) {
            throw FeatureException::invalidOptions($key);
        }

        return new self($key, FeatureType::Choice, options: $options);
    }

    /**
     * @throws FeatureException when the key does not have the form module.feature
     */
    public static function disabled(string $key): self
    {
        return new self($key, null);
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
