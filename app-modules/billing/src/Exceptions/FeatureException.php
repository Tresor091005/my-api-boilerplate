<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class FeatureException extends AssertionException
{
    public static function alreadyRegistered(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.already_registered'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function notRegistered(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.not_registered'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function invalidKey(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.invalid_key'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function counterMissing(string $featureKey, string $class, string $method): self
    {
        return new self(__('billing::exceptions.feature.counter_missing'), [
            'feature_key' => $featureKey,
            'counter'     => $class.'::'.$method,
        ]);
    }

    public static function invalidOptions(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.invalid_options'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function valueRequired(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.value_required'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function notCapacity(string $featureKey): self
    {
        return new self(__('billing::exceptions.feature.not_capacity'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function negativeQuantity(string $featureKey, int $quantity): self
    {
        return new self(__('billing::exceptions.feature.negative_quantity'), [
            'feature_key' => $featureKey,
            'quantity'    => $quantity,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
