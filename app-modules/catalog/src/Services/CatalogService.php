<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Shared\Support\HandleGenerator;

final class CatalogService implements CatalogInterface
{
    public function __construct(
        private TransactionalCatalogItemService $transactionalCatalogItemService,
    ) {}

    public function createService(ServiceData $data): Service
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($data, $organizationId): Service {
            $catalogItem = $this->transactionalCatalogItemService->createItem(
                CatalogItemType::Service,
                $organizationId,
                $data->name,
                $data->sku,
                $data->unitGroupId,
                $data->isActive,
            );

            $service = Service::query()->create([
                'id'              => $catalogItem->id,
                'organization_id' => $organizationId,
                'handle'          => HandleGenerator::generate(
                    $data->name,
                    'catalog_services',
                    extra: ['organization_id' => $organizationId],
                ),
                'name' => $data->name,
            ]);

            if ($data->templates->isNotEmpty()) {
                $now = now();
                $service->deliverableTemplates()->insert(
                    $data->templates->map(fn (array $template): array => [
                        'id'              => Str::uuid7()->toString(),
                        'organization_id' => $organizationId,
                        'service_id'      => $service->id,
                        'name'            => $template['name'],
                        'position'        => $template['position'],
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ])->all()
                );
            }

            return $service->load('deliverableTemplates');
        });
    }

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
