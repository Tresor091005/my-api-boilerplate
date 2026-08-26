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
