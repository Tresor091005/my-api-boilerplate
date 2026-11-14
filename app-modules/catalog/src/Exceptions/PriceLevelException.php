<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Shared\Exceptions\AssertionException;

final class PriceLevelException extends AssertionException
{
    public static function nameTaken(string $name, string $currencyCode): self
    {
        return new self(
            __('catalog::exceptions.price_level_name_taken'),
            ['name' => $name, 'currency_code' => $currencyCode],
        );
    }

    public static function currencyNotEnabled(string $currencyCode): self
    {
        return new self(
            __('catalog::exceptions.price_level_currency_not_enabled'),
            ['currency_code' => $currencyCode],
        );
    }

    public static function inUse(PriceLevel $priceLevel): self
    {
        return new self(
            __('catalog::exceptions.price_level_in_use'),
            ['price_level_id' => $priceLevel->id],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
