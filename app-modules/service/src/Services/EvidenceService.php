<?php

declare(strict_types=1);

namespace Lahatre\Service\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Service\Data\EvidenceData;
use Lahatre\Service\Enums\CommitmentStatus;
use Lahatre\Service\Enums\DeliverableStatus;
use Lahatre\Service\Enums\EvidenceStatus;
use Lahatre\Service\Exceptions\ServiceException;
use Lahatre\Service\Models\Evidence;
use Lahatre\Service\Models\ServiceCommitment;
use Lahatre\Service\Models\ServiceDeliverable;

final class EvidenceService
{
    public function create(ServiceDeliverable $deliverable, EvidenceData $data): Evidence
    {
        return DB::transaction(function () use ($deliverable, $data): Evidence {
            $lockedDeliverable = ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($deliverable->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDeliverable->status !== DeliverableStatus::Pending) {
                throw ServiceException::invalidDeliverableState();
            }

            return $lockedDeliverable->evidences()->create([
                'organization_id' => currentOrganizationId(),
                'status'          => EvidenceStatus::Draft,
                'token'           => Str::uuid7(),
                'snapshot'        => $data->snapshot,
                'expires_at'      => $data->expiresAt,
            ]);
        });
    }

    public function send(Evidence $evidence): Evidence
    {
        return DB::transaction(function () use ($evidence): Evidence {
            $locked = $this->lock($evidence);
            if ($locked->status !== EvidenceStatus::Draft) {
                throw ServiceException::invalidEvidenceState();
            }
            $locked->forceFill([
                'status'       => EvidenceStatus::Sent,
                'submitted_at' => now(),
            ])->save();

            return $locked;
        });
    }

    public function accept(Evidence $evidence, string $verifierIdentifier): Evidence
    {
        return DB::transaction(function () use ($evidence, $verifierIdentifier): Evidence {
            $locked = $this->lock($evidence);
            if ($locked->status === EvidenceStatus::Accepted) {
                return $locked;
            }
            if ($locked->status !== EvidenceStatus::Sent
                || ($locked->expires_at !== null && $locked->expires_at->isPast())) {
                throw ServiceException::invalidEvidenceState();
            }

            $deliverable = ServiceDeliverable::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($locked->deliverable_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deliverable->status === DeliverableStatus::Pending) {
                $commitment = ServiceCommitment::query()
                    ->where('organization_id', currentOrganizationId())
                    ->whereKey($deliverable->commitment_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($commitment->status !== CommitmentStatus::Active) {
                    throw ServiceException::invalidCommitmentState();
                }

                $deliverable->forceFill([
                    'status'       => DeliverableStatus::Completed,
                    'completed_at' => now(),
                ])->save();

                if (!$commitment->deliverables()->where('status', DeliverableStatus::Pending)->exists()) {
                    $commitment->forceFill([
                        'status'       => 'completed',
                        'completed_at' => now(),
                    ])->save();
                }
            }

            $locked->forceFill([
                'status'              => EvidenceStatus::Accepted,
                'accepted_at'         => now(),
                'verifier_identifier' => $verifierIdentifier,
            ])->save();

            return $locked;
        });
    }

    public function reject(Evidence $evidence, string $verifierIdentifier): Evidence
    {
        return DB::transaction(function () use ($evidence, $verifierIdentifier): Evidence {
            $locked = $this->lock($evidence);
            if ($locked->status !== EvidenceStatus::Sent) {
                throw ServiceException::invalidEvidenceState();
            }
            $locked->forceFill([
                'status'              => EvidenceStatus::Rejected,
                'rejected_at'         => now(),
                'verifier_identifier' => $verifierIdentifier,
            ])->save();

            return $locked;
        });
    }

    private function lock(Evidence $evidence): Evidence
    {
        return Evidence::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($evidence->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
