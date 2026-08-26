<?php

declare(strict_types=1);

namespace Lahatre\Organization\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class OrganizationException extends AssertionException
{
    public static function contextRequired(): self
    {
        return self::message(__('organization::exceptions.context_required'));
    }

    public static function functionalCurrencyImmutable(): self
    {
        return self::message(__('organization::exceptions.functional_currency_immutable'));
    }

    public static function organizationMismatch(): self
    {
        return self::message(__('organization::exceptions.exchange_rate_organization_mismatch'));
    }

    public static function currencyNotFound(string $code): self
    {
        return self::message(__('organization::exceptions.currency_not_found', ['code' => $code]), [
            'currency_code' => $code,
        ]);
    }

    public static function currencyNotEnabled(string $code): self
    {
        return self::message(__('organization::exceptions.currency_not_enabled', ['code' => $code]), [
            'currency_code' => $code,
        ]);
    }

    public static function functionalCurrencyMustBeEnabled(): self
    {
        return self::message(__('organization::exceptions.functional_currency_must_be_enabled'));
    }

    public static function sameCurrencyPair(): self
    {
        return self::message(__('organization::exceptions.same_currency_pair'));
    }

    public static function duplicateRate(): self
    {
        return self::message(__('organization::exceptions.duplicate_exchange_rate'));
    }

    public static function invalidRate(): self
    {
        return self::message(__('organization::exceptions.invalid_exchange_rate'));
    }

    public static function effectiveRateImmutable(): self
    {
        return self::message(__('organization::exceptions.effective_rate_immutable'));
    }

    public static function rateUnavailable(string $sourceCode, string $targetCode, string $context = 'default'): self
    {
        return self::message(__('organization::exceptions.exchange_rate_unavailable', [
            'source'  => $sourceCode,
            'target'  => $targetCode,
            'context' => $context,
        ]), [
            'source_currency_code' => $sourceCode,
            'target_currency_code' => $targetCode,
            'context'              => $context,
        ]);
    }

    private static function message(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
