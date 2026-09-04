<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Database\Factories\CatalogItemFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Service\Contracts\ServiceInterface;
use Lahatre\Service\Database\Factories\EvidenceFactory;
use Lahatre\Service\Database\Factories\ServiceCommitmentFactory;
use Lahatre\Service\Database\Factories\ServiceDeliverableFactory;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Service\Enums\EvidenceStatus;
use Lahatre\Service\Exceptions\ServiceException;
use Lahatre\Service\Models\ServiceCommitment;
use Lahatre\Service\Services\EvidenceService;
use Lahatre\Service\Services\ServiceService;

it('binds the service module public contract to its service implementation', function (): void {
    expect(app(ServiceInterface::class))->toBeInstanceOf(ServiceService::class);
});

describe('commitment persistence', function (): void {
    uses(RefreshDatabase::class);

    beforeEach(function (): void {
        $this->organizationId = Str::uuid7()->toString();
        DB::table('organization_organizations')->insert([
            'id'                       => $this->organizationId,
            'name'                     => 'Service Test Organization',
            'functional_currency_code' => 'XOF',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
        setPermissionsTeamId($this->organizationId);
    });

    it('creates one draft commitment with a snapshot of service templates', function (): void {
        $catalogItem = CatalogItemFactory::new()->create([
            'organization_id' => $this->organizationId,
            'item_type'       => CatalogItemType::Service,
            'is_stockable'    => false,
        ]);
        $serviceId = $catalogItem->id;
        DB::table('catalog_services')->insert([
            'id'              => $serviceId,
            'organization_id' => $this->organizationId,
            'name'            => 'Website delivery',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('catalog_service_deliverable_templates')->insert([
            'id'              => Str::uuid7()->toString(),
            'organization_id' => $this->organizationId,
            'service_id'      => $serviceId,
            'name'            => 'Launch',
            'position'        => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $commitment = app(ServiceInterface::class)->createCommitment(
            $serviceId,
            $saleLineId = Str::uuid7()->toString(),
        );

        $sameCommitment = app(ServiceInterface::class)->createCommitment($serviceId, $saleLineId);

        expect($commitment)->toBeInstanceOf(ServiceCommitment::class)
            ->and($commitment->status)->toBe(CommitmentStatus::Draft)
            ->and($commitment->deliverables)->toHaveCount(1)
            ->and($commitment->deliverables->first()->name)->toBe('Launch')
            ->and($sameCommitment->is($commitment))->toBeTrue()
            ->and($catalogItem->is_stockable)->toBeFalse();
    });

    it('does not accept evidence for a draft commitment', function (): void {
        $serviceId = Str::uuid7()->toString();
        DB::table('catalog_services')->insert([
            'id'              => $serviceId,
            'organization_id' => $this->organizationId,
            'name'            => 'Website delivery',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $commitment = ServiceCommitmentFactory::new()->create([
            'organization_id' => $this->organizationId,
            'service_id'      => $serviceId,
            'status'          => CommitmentStatus::Draft,
        ]);
        $deliverable = ServiceDeliverableFactory::new()->create([
            'organization_id' => $this->organizationId,
            'commitment_id'   => $commitment->id,
            'status'          => DeliverableStatus::Pending,
        ]);
        $evidence = EvidenceFactory::new()->create([
            'organization_id' => $this->organizationId,
            'deliverable_id'  => $deliverable->id,
            'status'          => EvidenceStatus::Sent,
            'submitted_at'    => now(),
        ]);

        expect(fn () => app(EvidenceService::class)->accept($evidence, 'verifier'))
            ->toThrow(ServiceException::class);

        expect($evidence->refresh()->status)->toBe(EvidenceStatus::Sent)
            ->and($deliverable->refresh()->status)->toBe(DeliverableStatus::Pending)
            ->and($commitment->refresh()->status)->toBe(CommitmentStatus::Draft);
    });
});
