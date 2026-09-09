<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;

final readonly class CatalogService implements CatalogInterface
{
    /**
     * @return Collection<int, array{id: string, name: string, position: int}>
     */
    public function serviceCommitmentTemplates(string $serviceId): Collection
    {
        $service = Service::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($serviceId)
            ->first();

        if (!$service instanceof Service) {
            return collect();
        }

        return $service->deliverableTemplates()
            ->get(['id', 'name', 'position'])
            ->map(fn (ServiceDeliverableTemplate $template): array => [
                'id'       => (string) $template->id,
                'name'     => (string) $template->name,
                'position' => (int) $template->position,
            ]);
    }
}
