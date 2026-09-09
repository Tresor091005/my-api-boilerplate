<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Service;
use Lahatre\Shared\Exceptions\AssertionException;

final class CatalogServiceException extends AssertionException
{
    public static function deliverableTemplatesRequired(?Service $service = null): self
    {
        return new self(
            __('catalog::exceptions.service_deliverable_templates_required'),
            $service instanceof Service ? ['service_id' => $service->id] : [],
        );
    }

    /** @param list<string> $templateIds */
    public static function deliverableTemplatesUnavailable(Service $service, array $templateIds): self
    {
        return new self(
            __('catalog::exceptions.service_deliverable_templates_unavailable'),
            ['service_id' => $service->id, 'template_ids' => $templateIds],
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
