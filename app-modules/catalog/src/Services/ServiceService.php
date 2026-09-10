<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\ServiceAssertion;
use Lahatre\Catalog\Data\CatalogItemUpdateData;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Data\ServiceDeliverableTemplateData;
use Lahatre\Catalog\Data\ServiceFilterData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

final readonly class ServiceService
{
    public function __construct(
        private TransactionalCatalogItemService $transactionalCatalogItemService,
        private ServiceAssertion $serviceAssertion,
    ) {}

    public function paginate(ServiceFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            $this->servicesQuery($filters)->with(responseRelationsToLoad()),
            $filters,
            tieBreakerColumn: 'catalog_services.id',
        );
    }

    public function retrieve(Service $service): Service
    {
        $this->assertTenant($service);

        return $service->load(responseRelationsToLoad());
    }

    public function create(ServiceData $data): Service
    {
        $organizationId = currentOrganizationId();
        $name = required($data->name);
        $templates = required($data->deliverableTemplates);

        $service = DB::transaction(function () use ($data, $organizationId, $name, $templates): Service {
            $this->serviceAssertion->assertHasDeliverableTemplates($templates);

            $catalogItem = $this->transactionalCatalogItemService->createItem(
                CatalogItemType::Service,
                $organizationId,
                $name,
                required($data->sku),
                required($data->unitGroupId),
                required($data->isActive),
            );

            $service = new Service;
            $service->id = $catalogItem->id;
            $service->organization_id = $organizationId;
            $service->handle = HandleGenerator::generate(
                $name,
                $service->getTable(),
                extra: ['organization_id' => $organizationId],
            );
            $service->name = $name;
            $service->save();

            $this->insertDeliverableTemplates($service, $templates);

            return $service;
        });

        return $service->load(responseRelationsToLoad());
    }

    public function update(Service $service, ServiceData $data): Service
    {
        $organizationId = currentOrganizationId();

        $service = DB::transaction(function () use ($service, $data, $organizationId): Service {
            $lockedService = $this->lockService($service, $organizationId);

            /** @var CatalogItem $catalogItem */
            $catalogItem = $lockedService->catalogItem()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedService->fill(withoutMissing(['name' => $data->name]));
            $lockedService->save();

            $this->transactionalCatalogItemService->update(
                $catalogItem,
                new CatalogItemUpdateData(
                    sku: $data->sku ?? MissingValue::Instance,
                    isActive: $data->isActive,
                    inventory: MissingValue::Instance,
                ),
            );

            if (!$data->deliverableTemplates instanceof MissingValue) {
                $this->synchronizeDeliverableTemplates($lockedService, $data->deliverableTemplates);
            }

            return $lockedService;
        });

        return $service->load(responseRelationsToLoad());
    }

    public function delete(Service $service): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($service, $organizationId): void {
            $lockedService = $this->lockService($service, $organizationId);

            /** @var CatalogItem $catalogItem */
            $catalogItem = $lockedService->catalogItem()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedService->deliverableTemplates()->delete();
            $lockedService->delete();
            $this->transactionalCatalogItemService->delete($catalogItem);
        });
    }

    /** @return Builder<Service> */
    private function servicesQuery(ServiceFilterData $filters): Builder
    {
        $organizationId = currentOrganizationId();

        /** @var Builder<Service> $query */
        $query = Service::query();
        $query->join('catalog_items', function (JoinClause $join): void {
            $join->on('catalog_items.id', '=', 'catalog_services.id')
                ->on('catalog_items.organization_id', '=', 'catalog_services.organization_id');
        })
            ->where('catalog_services.organization_id', $organizationId)
            ->where('catalog_items.organization_id', $organizationId)
            ->where('catalog_items.item_type', CatalogItemType::Service->value)
            ->whereNull('catalog_items.deleted_at')
            ->select([
                'catalog_services.*',
                'catalog_items.sku as catalog_item_sku',
                'catalog_items.is_active as catalog_item_is_active',
            ]);

        if ($filters->handle !== null) {
            $query->where('catalog_services.handle', 'like', "{$filters->handle}%");
        }
        if ($filters->name !== null) {
            $query->where('catalog_services.name', 'like', "{$filters->name}%");
        }
        if ($filters->sku !== null) {
            $query->where('catalog_items.sku', 'like', "{$filters->sku}%");
        }
        if ($filters->isActive !== null) {
            $query->where('catalog_items.is_active', $filters->isActive);
        }

        return $query;
    }

    /** @param Collection<int, ServiceDeliverableTemplateData> $templates */
    private function insertDeliverableTemplates(Service $service, Collection $templates): void
    {
        $now = now();
        $rows = $templates->values()->map(
            fn (ServiceDeliverableTemplateData $template, int $index): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $service->organization_id,
                'service_id'      => $service->id,
                'name'            => $template->name,
                'position'        => $index + 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        );

        ServiceDeliverableTemplate::insert($rows->all());
    }

    /** @param Collection<int, ServiceDeliverableTemplateData> $templates */
    private function synchronizeDeliverableTemplates(Service $service, Collection $templates): void
    {
        $this->serviceAssertion->assertHasDeliverableTemplates($templates, $service);

        /** @var EloquentCollection<string, ServiceDeliverableTemplate> $existingTemplates */
        $existingTemplates = $service->deliverableTemplates()
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $this->serviceAssertion->assertTemplatesCanBeSynchronized($service, $templates, $existingTemplates);

        $retainedIds = $templates
            ->pluck('id')
            ->reject(fn (mixed $id): bool => $id instanceof MissingValue)
            ->values();
        $query = $service->deliverableTemplates();

        if ($retainedIds->isEmpty()) {
            $query->forceDelete();
        } else {
            $query->whereNotIn('id', $retainedIds->all())->forceDelete();

            $positionOffset = ((int) $existingTemplates->max('position')) + $templates->count() + 1;
            $service->deliverableTemplates()
                ->whereIn('id', $retainedIds->all())
                ->increment('position', $positionOffset);
        }

        $now = now();
        $rows = $templates->values()->map(
            fn (ServiceDeliverableTemplateData $template, int $index): array => [
                'id'              => $template->id instanceof MissingValue ? (string) Str::uuid7() : $template->id,
                'organization_id' => $service->organization_id,
                'service_id'      => $service->id,
                'name'            => $template->name,
                'position'        => $index + 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        );

        ServiceDeliverableTemplate::query()->upsert(
            $rows->all(),
            ['id'],
            ['name', 'position', 'updated_at'],
        );
    }

    private function lockService(Service $service, string $organizationId): Service
    {
        $this->assertTenant($service);

        /** @var Service $model */
        $model = Service::query()
            ->where('organization_id', $organizationId)
            ->whereKey($service->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $model;
    }

    private function assertTenant(Service $service): void
    {
        if ($service->organization_id !== currentOrganizationId()) {
            throw (new ModelNotFoundException)->setModel(Service::class, [$service->id]);
        }
    }
}
