<?php

declare(strict_types=1);

namespace Lahatre\Service\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Service\Contracts\ServiceInterface;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Service\Exceptions\ServiceException;
use Lahatre\Service\Models\ServiceCommitment;
use Lahatre\Service\Models\ServiceDeliverable;

final class ServiceService implements ServiceInterface
{
    public function __construct(
        private CatalogInterface $catalog,
    ) {}

    public function createCommitment(string $serviceId, string $saleLineId): ServiceCommitment
    {
        $organizationId = currentOrganizationId();
        $templates = $this->catalog->serviceCommitmentTemplates($serviceId);

        if ($templates->isEmpty()) {
            throw ServiceException::serviceTemplatesRequired();
        }

        return DB::transaction(function () use ($organizationId, $serviceId, $saleLineId, $templates): ServiceCommitment {
            $existing = ServiceCommitment::query()
                ->where('organization_id', $organizationId)
                ->where('sale_line_id', $saleLineId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ServiceCommitment) {
                if ($existing->service_id !== $serviceId) {
                    throw ServiceException::commitmentAlreadyExists();
                }

                return $existing->load('deliverables');
            }

            $commitmentId = Str::uuid7()->toString();
            $now = now();
            $inserted = DB::table('service_commitments')->insertOrIgnore([
                'id'              => $commitmentId,
                'organization_id' => $organizationId,
                'service_id'      => $serviceId,
                'sale_line_id'    => $saleLineId,
                'status'          => CommitmentStatus::Draft->value,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $commitment = ServiceCommitment::query()
                ->where('organization_id', $organizationId)
                ->where('sale_line_id', $saleLineId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inserted === 0) {
                if ($commitment->service_id !== $serviceId) {
                    throw ServiceException::commitmentAlreadyExists();
                }

                return $commitment->load('deliverables');
            }

            $commitment->deliverables()->createMany(
                $templates->map(fn (array $template): array => [
                    'organization_id' => $organizationId,
                    'name'            => $template['name'],
                    'position'        => $template['position'],
                    'status'          => DeliverableStatus::Pending,
                ])->all()
            );

            return $commitment->load('deliverables');
        });
    }

    public function confirmCommitment(ServiceCommitment $commitment): ServiceCommitment
    {
        $this->assertTenant($commitment);

        return DB::transaction(function () use ($commitment): ServiceCommitment {
            $locked = ServiceCommitment::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($commitment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CommitmentStatus::Draft) {
                throw ServiceException::invalidCommitmentState();
            }

            $locked->forceFill([
                'status'       => CommitmentStatus::Active,
                'confirmed_at' => now(),
            ])->save();

            return $locked->load('deliverables');
        });
    }

    public function closeCommitment(ServiceCommitment $commitment): ServiceCommitment
    {
        $this->assertTenant($commitment);

        return DB::transaction(function () use ($commitment): ServiceCommitment {
            $locked = ServiceCommitment::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($commitment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($locked->status, [CommitmentStatus::Draft, CommitmentStatus::Active], true)) {
                throw ServiceException::invalidCommitmentState();
            }

            ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->where('commitment_id', $locked->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            $locked->forceFill([
                'status'    => CommitmentStatus::Closed,
                'closed_at' => now(),
            ])->save();

            return $locked->load('deliverables');
        });
    }

    private function assertTenant(ServiceCommitment $commitment): void
    {
        if ($commitment->organization_id !== currentOrganizationId()) {
            throw ServiceException::invalidCommitmentState();
        }
    }
}
