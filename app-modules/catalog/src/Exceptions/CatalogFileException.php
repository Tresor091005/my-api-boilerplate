<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class CatalogFileException extends AssertionException
{
    public static function serviceInactive(string $serviceId): self
    {
        return new self(__('catalog::exceptions.file_service_inactive'), ['service_id' => $serviceId]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
