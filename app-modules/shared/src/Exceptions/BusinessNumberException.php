<?php

declare(strict_types=1);

namespace Lahatre\Shared\Exceptions;

/**
 * Business-rule violations for business number definitions and generation.
 */
final class BusinessNumberException extends AssertionException
{
    public static function definitionNotFound(string $key): self
    {
        return self::message(
            __('shared::exceptions.business_number_definition_not_found'),
            ['key' => $key],
        );
    }

    public static function invalidDefinition(string $key, string $reason): self
    {
        return self::message(
            __('shared::exceptions.invalid_business_number_definition'),
            ['key' => $key, 'reason' => $reason],
        );
    }

    private static function message(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
