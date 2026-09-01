<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Shared\Exceptions\AssertionException;

final class CatalogItemException extends AssertionException
{
    public static function usedByBundle(CatalogItem $catalogItem): self
    {
        return new self(
            __('catalog::exceptions.catalog_item_used_by_bundle'),
            ['catalog_item_id' => $catalogItem->id],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
