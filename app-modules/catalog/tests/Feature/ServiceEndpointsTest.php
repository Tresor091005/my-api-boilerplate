<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Data\ServiceFilterData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\CatalogServiceException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Catalog\Services\ServiceService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->serviceService = app(ServiceService::class);
    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
});

it('manages a service and its ordered deliverable templates as one aggregate', function (): void {
    $service = $this->serviceService->create(ServiceData::fromArray([
        'name'                  => 'On-site Installation',
        'sku'                   => 'SERVICE-INSTALL',
        'unit_group_id'         => $this->unitGroup->id,
        'is_active'             => true,
        'deliverable_templates' => [
            ['name' => 'Site survey'],
            ['name' => 'Installation report'],
        ],
    ]));

    $catalogItem = CatalogItem::query()->findOrFail($service->id);
    $templates = $service->deliverableTemplates()->get();

    expect($catalogItem->item_type)->toBe(CatalogItemType::Service)
        ->and($catalogItem->is_stockable)->toBeFalse()
        ->and($service->handle)->toBe('on-site-installation')
        ->and($templates->pluck('position')->all())->toBe([1, 2]);

    $preservedTemplateId = $templates[1]->id;
    $removedTemplateId = $templates[0]->id;
    $updated = $this->serviceService->update($service, ServiceData::fromArray([
        'name'                  => 'Premium Installation',
        'is_active'             => false,
        'deliverable_templates' => [
            ['id' => $preservedTemplateId, 'name' => 'Signed installation report'],
            ['name' => 'Customer training'],
        ],
    ], missingFields: ['sku', 'unit_group_id']));

    $updatedTemplates = $updated->deliverableTemplates;
    expect($updated->name)->toBe('Premium Installation')
        ->and($updated->handle)->toBe('on-site-installation')
        ->and($updated->catalogItem->is_active)->toBeFalse()
        ->and($updatedTemplates->pluck('position')->all())->toBe([1, 2])
        ->and($updatedTemplates->first()->id)->toBe($preservedTemplateId)
        ->and(ServiceDeliverableTemplate::withTrashed()->whereKey($removedTemplateId)->exists())->toBeFalse();

    $pageIds = collect($this->serviceService->paginate(ServiceFilterData::fromArray([
        'name' => 'Premium',
    ]))->items())->pluck('id');
    expect($pageIds)->toContain($service->id);

    $this->serviceService->delete($updated);

    expect(Service::query()->whereKey($service->id)->exists())->toBeFalse()
        ->and(Service::withTrashed()->whereKey($service->id)->exists())->toBeTrue()
        ->and(CatalogItem::query()->whereKey($service->id)->exists())->toBeFalse()
        ->and(ServiceDeliverableTemplate::query()->where('service_id', $service->id)->count())->toBe(0)
        ->and(ServiceDeliverableTemplate::withTrashed()->where('service_id', $service->id)->count())->toBe(2)
        ->and(ServiceDeliverableTemplate::withTrashed()
            ->where('service_id', $service->id)
            ->whereNull('deleted_at')
            ->count())->toBe(0);

    $replacement = $this->serviceService->create(ServiceData::fromArray([
        'name'                  => 'On-site Installation',
        'unit_group_id'         => $this->unitGroup->id,
        'deliverable_templates' => [['name' => 'Replacement report']],
    ]));

    expect($replacement->handle)->toBe('on-site-installation-1');
});

it('rejects a deliverable template owned by another service', function (): void {
    $first = $this->serviceService->create(ServiceData::fromArray([
        'name'                  => 'First service',
        'unit_group_id'         => $this->unitGroup->id,
        'deliverable_templates' => [['name' => 'First output']],
    ]));
    $second = $this->serviceService->create(ServiceData::fromArray([
        'name'                  => 'Second service',
        'unit_group_id'         => $this->unitGroup->id,
        'deliverable_templates' => [['name' => 'Second output']],
    ]));

    expect(fn (): Service => $this->serviceService->update($first, ServiceData::fromArray([
        'deliverable_templates' => [[
            'id'   => $second->deliverableTemplates()->firstOrFail()->id,
            'name' => 'Stolen output',
        ]],
    ], missingFields: ['name', 'sku', 'unit_group_id', 'is_active'])))
        ->toThrow(CatalogServiceException::class);
});

it('hides services from another tenant', function (): void {
    $catalogItem = CatalogItem::factory()->service()->create([
        'organization_id' => $this->otherOrganizationId,
        'unit_group_id'   => $this->unitGroup->id,
    ]);
    $service = Service::factory()->forCatalogItem($catalogItem)->create();

    expect(fn (): Service => $this->serviceService->retrieve($service))
        ->toThrow(ModelNotFoundException::class);
});
