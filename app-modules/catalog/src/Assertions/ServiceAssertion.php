<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\ServiceDeliverableTemplateData;
use Lahatre\Catalog\Exceptions\CatalogServiceException;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Shared\Data\MissingValue;

final class ServiceAssertion
{
    /**
     * Assert that a service keeps at least one deliverable template.
     *
     * @param  Collection<int, ServiceDeliverableTemplateData>  $templates
     *
     * @throws CatalogServiceException If the template collection is empty.
     */
    public function assertHasDeliverableTemplates(Collection $templates, ?Service $service = null): void
    {
        if ($templates->isEmpty()) {
            throw CatalogServiceException::deliverableTemplatesRequired($service);
        }
    }

    /**
     * Assert that every supplied template identifier belongs to the service.
     *
     * @param  Collection<int, ServiceDeliverableTemplateData>  $templates
     * @param  EloquentCollection<string, ServiceDeliverableTemplate>  $existingTemplates
     *
     * @throws CatalogServiceException If an identifier is duplicated or unavailable.
     */
    public function assertTemplatesCanBeSynchronized(
        Service $service,
        Collection $templates,
        EloquentCollection $existingTemplates,
    ): void {
        $requestedIds = $templates
            ->map(fn (ServiceDeliverableTemplateData $template): ?string => $template->id instanceof MissingValue
                ? null
                : $template->id)
            ->filter()
            ->values();
        $unavailableIds = $requestedIds
            ->duplicates()
            ->merge($requestedIds->reject(fn (string $id): bool => $existingTemplates->has($id)))
            ->unique()
            ->values()
            ->all();

        if ($unavailableIds !== []) {
            throw CatalogServiceException::deliverableTemplatesUnavailable($service, $unavailableIds);
        }
    }
}
