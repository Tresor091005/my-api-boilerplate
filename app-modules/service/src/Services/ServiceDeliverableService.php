<?php

declare(strict_types=1);

namespace Lahatre\Service\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Service\Data\ServiceDeliverableData;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Service\Exceptions\ServiceException;
use Lahatre\Service\Models\ServiceCommitment;
use Lahatre\Service\Models\ServiceDeliverable;
use Lahatre\Shared\Data\MissingValue;

final class ServiceDeliverableService
{
    public function create(ServiceCommitment $commitment, ServiceDeliverableData $data): ServiceDeliverable
    {
        return DB::transaction(function () use ($commitment, $data): ServiceDeliverable {
            $lockedCommitment = $this->lockCommitment($commitment->id);
            $this->assertDraft($lockedCommitment);

            return $lockedCommitment->deliverables()->create([
                'organization_id' => currentOrganizationId(),
                'name'            => $data->name instanceof MissingValue ? '' : $data->name,
                'position'        => $data->position instanceof MissingValue ? 0 : $data->position,
                'status'          => DeliverableStatus::Pending,
            ]);
        });
    }

    public function update(ServiceDeliverable $deliverable, ServiceDeliverableData $data): ServiceDeliverable
    {
        return DB::transaction(function () use ($deliverable, $data): ServiceDeliverable {
            $lockedDeliverable = ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($deliverable->id)
                ->lockForUpdate()
                ->firstOrFail();
            $commitment = $this->lockCommitment($lockedDeliverable->commitment_id);
            $this->assertDraft($commitment);

            $lockedDeliverable->fill(array_filter([
                'name'     => $data->name instanceof MissingValue ? null : $data->name,
                'position' => $data->position instanceof MissingValue ? null : $data->position,
            ], static fn (mixed $value): bool => $value !== null));
            $lockedDeliverable->save();

            return $lockedDeliverable;
        });
    }

    public function delete(ServiceDeliverable $deliverable): void
    {
        DB::transaction(function () use ($deliverable): void {
            $lockedDeliverable = ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($deliverable->id)
                ->lockForUpdate()
                ->firstOrFail();
            $commitment = $this->lockCommitment($lockedDeliverable->commitment_id);
            $this->assertDraft($commitment);

            $lockedDeliverable->delete();
        });
    }

    public function complete(ServiceDeliverable $deliverable): ServiceDeliverable
    {
        return DB::transaction(function () use ($deliverable): ServiceDeliverable {
            $locked = ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($deliverable->id)
                ->lockForUpdate()
                ->firstOrFail();
            $commitment = $locked->commitment()->lockForUpdate()->firstOrFail();

            if ($commitment->status !== CommitmentStatus::Active || $locked->status !== DeliverableStatus::Pending) {
                throw ServiceException::invalidDeliverableState();
            }

            $locked->forceFill([
                'status'       => DeliverableStatus::Completed,
                'completed_at' => now(),
            ])->save();

            $remaining = $commitment->deliverables()
                ->where('status', DeliverableStatus::Pending)
                ->exists();
            if (!$remaining) {
                $commitment->forceFill([
                    'status'       => CommitmentStatus::Completed,
                    'completed_at' => now(),
                ])->save();
            }

            return $locked;
        });
    }

    private function assertDraft(ServiceCommitment $commitment): void
    {
        if ($commitment->organization_id !== currentOrganizationId()
            || $commitment->status !== CommitmentStatus::Draft) {
            throw ServiceException::invalidCommitmentState();
        }
    }

    private function lockCommitment(string $commitmentId): ServiceCommitment
    {
        return ServiceCommitment::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($commitmentId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
