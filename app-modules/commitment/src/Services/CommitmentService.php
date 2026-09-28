<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Services;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Service as CatalogService;
use Lahatre\Commitment\Data\CommitmentData;
use Lahatre\Commitment\Data\DeliverableData;
use Lahatre\Commitment\Data\EvidenceData;
use Lahatre\Commitment\Enums\CommitmentEvent;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Commitment\Enums\DeliverableEvent;
use Lahatre\Commitment\Enums\DeliverableState;
use Lahatre\Commitment\Enums\ReviewEvent;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Commitment\Exceptions\CommitmentException;
use Lahatre\Commitment\Models\ServiceAuditEvent;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Models\ServiceEvidence;
use Lahatre\Commitment\StateMachines\CommitmentMachine;
use Lahatre\Commitment\StateMachines\DeliverableMachine;
use Lahatre\Commitment\StateMachines\ReviewMachine;
use Lahatre\Customer\Models\Customer;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\StateMachine\StateMachineValue;

final readonly class CommitmentService
{
    public function __construct(private LibraryInterface $library) {}

    /** @return CursorPaginator<int, ServiceCommitment> */
    public function list(): CursorPaginator
    {
        return ServiceCommitment::query()
            ->where('organization_id', currentOrganizationId())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);
    }

    public function retrieve(string $id): ServiceCommitment
    {
        return ServiceCommitment::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($id)
            ->firstOrFail();
    }

    public function create(CommitmentData $data, ?string $actorId): ServiceCommitment
    {
        return DB::transaction(function () use ($data, $actorId): ServiceCommitment {
            $service = CatalogService::query()
                ->where('organization_id', currentOrganizationId())
                ->whereKey($data->serviceId)
                ->firstOrFail();
            Customer::query()
                ->where('organization_id', currentOrganizationId())
                ->where('is_active', true)
                ->whereKey($data->customerId)
                ->firstOrFail();
            $commitment = ServiceCommitment::query()->create([
                'organization_id'  => currentOrganizationId(),
                'service_id'       => $service->id,
                'customer_id'      => $data->customerId,
                'client_email'     => Str::lower($data->clientEmail),
                'public_reference' => Str::upper(Str::random(16)),
                'state'            => CommitmentState::Draft->value,
            ]);
            ServiceCommitmentProposal::query()->create([
                'organization_id' => currentOrganizationId(),
                'commitment_id'   => $commitment->id,
                'version'         => 1,
                'title'           => $data->title ?? $service->name,
                'terms'           => $data->terms,
                'state'           => 'draft',
            ]);
            $this->audit($commitment, 'created', $actorId);

            return $commitment;
        });
    }

    public function updateDraft(ServiceCommitment $commitment, string $title, MissingValue|string|null $terms, ?string $actorId): ServiceCommitmentProposal
    {
        return DB::transaction(function () use ($commitment, $title, $terms, $actorId): ServiceCommitmentProposal {
            $locked = $this->lock($commitment);
            if ($locked->state !== CommitmentState::Draft->value) {
                throw CommitmentException::invalidState();
            }
            $proposal = ServiceCommitmentProposal::query()
                ->where('organization_id', currentOrganizationId())
                ->where('commitment_id', $locked->id)
                ->where('state', 'draft')
                ->lockForUpdate()->firstOrFail();
            $proposal->fill(withoutMissing(['title' => $title, 'terms' => $terms]));
            $proposal->save();
            $this->audit($locked, 'draft_updated', $actorId, snapshot: ['proposal_id' => $proposal->id]);

            return $proposal;
        });
    }

    public function submitProposal(ServiceCommitment $commitment, ?string $title, MissingValue|string|null $terms, ?string $actorId): ServiceCommitmentProposal
    {
        return DB::transaction(function () use ($commitment, $title, $terms, $actorId): ServiceCommitmentProposal {
            $locked = $this->lock($commitment);
            if ($locked->state === CommitmentState::Draft->value) {
                $proposal = ServiceCommitmentProposal::query()
                    ->where('organization_id', currentOrganizationId())->where('commitment_id', $locked->id)
                    ->where('state', 'draft')->lockForUpdate()->firstOrFail();
                $proposal->update([
                    'title'        => $title ?? $proposal->title,
                    'terms'        => $terms instanceof MissingValue ? $proposal->terms : $terms,
                    'state'        => 'submitted',
                    'submitted_at' => now(),
                ]);
                $this->transition($locked, CommitmentEvent::Submit, $actorId);
            } elseif ($locked->state === CommitmentState::Active->value) {
                $version = (int) ServiceCommitmentProposal::query()
                    ->where('organization_id', currentOrganizationId())->where('commitment_id', $locked->id)
                    ->max('version');
                $accepted = ServiceCommitmentProposal::query()->whereKey($locked->accepted_proposal_id)->firstOrFail();
                $proposal = ServiceCommitmentProposal::query()->create([
                    'organization_id' => currentOrganizationId(),
                    'commitment_id'   => $locked->id,
                    'version'         => $version + 1,
                    'title'           => $title ?? $accepted->title,
                    'terms'           => $terms instanceof MissingValue ? $accepted->terms : $terms,
                    'state'           => 'submitted',
                    'submitted_at'    => now(),
                ]);
            } else {
                throw CommitmentException::invalidState();
            }
            $this->audit($locked, 'proposal_submitted', $actorId, snapshot: ['proposal_id' => $proposal->id]);

            return $proposal;
        });
    }

    public function addDeliverable(ServiceCommitment $commitment, DeliverableData $data, ?string $actorId): ServiceDeliverable
    {
        return DB::transaction(function () use ($commitment, $data, $actorId): ServiceDeliverable {
            $locked = $this->lock($commitment);
            if (!in_array($locked->state, [CommitmentState::Draft->value, CommitmentState::Active->value], true)) {
                throw CommitmentException::invalidState();
            }
            $this->assertQuantityUnit(MissingValue::required($data->quantity), MissingValue::required($data->displayUnitCode));
            $unit = ServiceDeliverable::query()->create([
                'organization_id'   => currentOrganizationId(),
                'commitment_id'     => $locked->id,
                'title'             => MissingValue::required($data->title),
                'description'       => $data->description,
                'scheduled_at'      => $data->scheduledAt,
                'quantity'          => $data->quantity,
                'display_unit_code' => $data->displayUnitCode,
                'execution_state'   => DeliverableState::Planned->value,
                'validation_state'  => ReviewState::Unsubmitted->value,
            ]);
            $this->audit($locked, 'deliverable_created', $actorId, $unit->id);

            return $unit;
        });
    }

    public function updateDeliverable(ServiceCommitment $commitment, ServiceDeliverable $unit, DeliverableData $data, ?string $actorId): ServiceDeliverable
    {
        return DB::transaction(function () use ($commitment, $unit, $data, $actorId): ServiceDeliverable {
            $locked = $this->lock($commitment);
            $current = $this->lockUnit($locked, $unit->id);
            if (!in_array($locked->state, [CommitmentState::Draft->value, CommitmentState::Active->value], true)) {
                throw CommitmentException::invalidState();
            }
            $current->fill(withoutMissing([
                'title'             => $data->title,
                'description'       => $data->description,
                'scheduled_at'      => $data->scheduledAt,
                'quantity'          => $data->quantity,
                'display_unit_code' => $data->displayUnitCode,
            ]));
            $this->assertQuantityUnit($current->quantity, $current->display_unit_code);
            $current->save();
            $this->audit($locked, 'deliverable_updated', $actorId, $current->id);

            return $current;
        });
    }

    public function submitEvidence(ServiceCommitment $commitment, ServiceDeliverable $unit, EvidenceData $data, ?string $actorId): ServiceEvidence
    {
        return DB::transaction(function () use ($commitment, $unit, $data, $actorId): ServiceEvidence {
            $locked = $this->lock($commitment);
            if ($locked->state !== CommitmentState::Active->value) {
                throw CommitmentException::invalidState();
            }
            $current = $this->lockUnit($locked, $unit->id);
            $previousExecutionState = $current->execution_state;
            $event = match ($data->outcome) {
                'performed'   => DeliverableEvent::MarkPerformed,
                'missed'      => DeliverableEvent::MarkMissed,
                'cancelled'   => DeliverableEvent::MarkCancelled,
                'rescheduled' => DeliverableEvent::Reschedule,
                default       => throw CommitmentException::invalidState(),
            };
            $execution = DeliverableMachine::define()->actor(DeliverableState::from($current->execution_state))
                ->trigger($event, fn () => CommitmentException::invalidState());
            $review = ReviewMachine::define()->actor(ReviewState::from($current->validation_state))
                ->trigger(ReviewEvent::Submit, fn () => CommitmentException::invalidState());
            $version = (int) ServiceEvidence::query()->where('organization_id', currentOrganizationId())
                ->where('deliverable_id', $current->id)->max('version');
            $evidence = ServiceEvidence::query()->create([
                'organization_id'   => currentOrganizationId(),
                'commitment_id'     => $locked->id,
                'deliverable_id'    => $current->id,
                'version'           => $version + 1,
                'title'             => $current->title,
                'description'       => $current->description,
                'outcome'           => $data->outcome,
                'narrative'         => $data->narrative,
                'reason'            => $data->reason,
                'scheduled_at'      => $data->scheduledAt ?? $current->scheduled_at,
                'performed_at'      => $data->performedAt,
                'quantity'          => $data->quantity ?? $current->quantity,
                'display_unit_code' => $data->displayUnitCode ?? $current->display_unit_code,
                'submitted_at'      => now(),
            ]);
            if ($data->fileIds !== []) {
                $this->library->addAttachments($evidence, 'evidence', $data->fileIds);
            }
            $current->update([
                'execution_state'     => StateMachineValue::normalize($execution->to),
                'validation_state'    => StateMachineValue::normalize($review->to),
                'current_evidence_id' => $evidence->id,
                'scheduled_at'        => $evidence->scheduled_at,
                'performed_at'        => $evidence->performed_at,
                'quantity'            => $evidence->quantity,
                'display_unit_code'   => $evidence->display_unit_code,
            ]);
            $this->audit($locked, 'evidence_submitted', $actorId, $current->id,
                from: $previousExecutionState,
                to: StateMachineValue::normalize($execution->to),
                snapshot: [
                    'evidence_id' => $evidence->id,
                    'review_from' => StateMachineValue::normalize($review->from),
                    'review_to'   => StateMachineValue::normalize($review->to),
                ]);

            return $evidence;
        });
    }

    public function requestClosure(ServiceCommitment $commitment, ?string $actorId): ServiceAuditEvent
    {
        return DB::transaction(function () use ($commitment, $actorId): ServiceAuditEvent {
            $locked = $this->lock($commitment);
            $this->transition($locked, CommitmentEvent::RequestClosure, $actorId);

            $event = $this->audit($locked, 'closure_requested', $actorId);
            $locked->update(['pending_lifecycle_request_id' => $event->id]);

            return $event;
        });
    }

    public function requestReopening(ServiceCommitment $commitment, ?string $actorId): ServiceAuditEvent
    {
        return DB::transaction(function () use ($commitment, $actorId): ServiceAuditEvent {
            $locked = $this->lock($commitment);
            $this->transition($locked, CommitmentEvent::RequestReopening, $actorId);

            $event = $this->audit($locked, 'reopening_requested', $actorId);
            $locked->update(['pending_lifecycle_request_id' => $event->id]);

            return $event;
        });
    }

    /** @return array<string, mixed> */
    public function report(ServiceCommitment $commitment): array
    {
        $locked = $this->retrieve($commitment->id);
        $units = ServiceDeliverable::query()
            ->where('organization_id', currentOrganizationId())->where('commitment_id', $locked->id)
            ->orderBy('created_at')->get();
        $acceptedIds = $units->pluck('accepted_evidence_id')->filter()->all();
        $accepted = ServiceEvidence::query()->where('organization_id', currentOrganizationId())
            ->whereIn('id', $acceptedIds)->get()->keyBy('id');

        return [
            'commitment_id' => $locked->id,
            'state'         => $locked->state,
            'confirmed'     => $units->filter(fn (ServiceDeliverable $unit): bool => $unit->accepted_evidence_id !== null && $unit->validation_state !== ReviewState::Disputed->value)
                ->map(fn (ServiceDeliverable $unit): array => [
                    'deliverable_id' => $unit->id,
                    'title'          => $accepted->get($unit->accepted_evidence_id)?->title,
                    'evidence'       => $accepted->get($unit->accepted_evidence_id)?->toArray(),
                ])->values()->all(),
            'pending_count'  => $units->where('validation_state', ReviewState::Pending->value)->count(),
            'disputed_count' => $units->whereIn('validation_state', [ReviewState::Rejected->value, ReviewState::Disputed->value, ReviewState::ChangesRequested->value])->count(),
        ];
    }

    public function lock(ServiceCommitment $commitment): ServiceCommitment
    {
        return ServiceCommitment::query()->where('organization_id', currentOrganizationId())
            ->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
    }

    private function assertQuantityUnit(?string $quantity, ?string $displayUnitCode): void
    {
        if (($quantity === null) !== ($displayUnitCode === null)) {
            throw CommitmentException::invalidQuantityUnit();
        }
    }

    public function lockUnit(ServiceCommitment $commitment, string $unitId): ServiceDeliverable
    {
        return ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->whereKey($unitId)->lockForUpdate()->firstOrFail();
    }

    public function transition(ServiceCommitment $commitment, CommitmentEvent $event, ?string $actorId, string $actorType = 'provider'): void
    {
        $result = CommitmentMachine::define()->actor(CommitmentState::from($commitment->state))
            ->trigger($event, fn () => CommitmentException::invalidState());
        $commitment->update(['state' => StateMachineValue::normalize($result->to)]);
        $this->audit($commitment, $event->value, $actorId, from: StateMachineValue::normalize($result->from), to: StateMachineValue::normalize($result->to), actorType: $actorType);
    }

    /** @param array<string, mixed>|null $snapshot */
    public function audit(ServiceCommitment $commitment, string $event, ?string $actorId, ?string $unitId = null, ?string $from = null, ?string $to = null, ?array $snapshot = null, string $actorType = 'provider', ?string $actorEmail = null): ServiceAuditEvent
    {
        return ServiceAuditEvent::query()->create([
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'deliverable_id'  => $unitId,
            'event_type'      => $event,
            'actor_type'      => $actorType,
            'actor_id'        => $actorId,
            'actor_email'     => $actorEmail,
            'from_state'      => $from,
            'to_state'        => $to,
            'snapshot'        => $snapshot,
            'created_at'      => now(),
        ]);
    }
}
