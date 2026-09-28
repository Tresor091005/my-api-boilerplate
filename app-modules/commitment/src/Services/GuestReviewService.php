<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Commitment\Data\ReviewBatchData;
use Lahatre\Commitment\Enums\CommitmentEvent;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Commitment\Enums\ReviewEvent;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Commitment\Exceptions\CommitmentException;
use Lahatre\Commitment\Models\ServiceAuditEvent;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Models\ServiceEvidence;
use Lahatre\Commitment\Models\ServiceReview;
use Lahatre\Commitment\StateMachines\CommitmentMachine;
use Lahatre\Commitment\StateMachines\ReviewMachine;
use Lahatre\Shared\StateMachine\StateMachineValue;

final readonly class GuestReviewService
{
    public function __construct(private CommitmentService $commitments) {}

    public function reviewProposal(ServiceCommitment $commitment, string $proposalId, string $decision, ?string $comment): ServiceReview
    {
        return DB::transaction(function () use ($commitment, $proposalId, $decision, $comment): ServiceReview {
            $locked = $this->lock($commitment);
            $proposal = ServiceCommitmentProposal::query()->where('organization_id', $locked->organization_id)
                ->where('commitment_id', $locked->id)->whereKey($proposalId)->lockForUpdate()->firstOrFail();
            if ($proposal->state !== 'submitted' || !in_array($locked->state, [CommitmentState::AwaitingAcceptance->value, CommitmentState::Active->value], true)) {
                throw CommitmentException::staleVersion();
            }
            $latest = ServiceCommitmentProposal::query()->where('organization_id', $locked->organization_id)
                ->where('commitment_id', $locked->id)->max('version');
            if ($proposal->version !== (int) $latest) {
                throw CommitmentException::staleVersion();
            }
            if (!in_array($decision, ['accept', 'request_changes'], true)) {
                throw CommitmentException::invalidReview();
            }
            $proposal->update(['state' => $decision === 'accept' ? 'accepted' : 'changes_requested', 'reviewed_at' => now()]);
            if ($decision === 'accept') {
                $locked->update(['accepted_proposal_id' => $proposal->id]);
            }
            if ($locked->state === CommitmentState::AwaitingAcceptance->value) {
                $this->transition($locked, $decision === 'accept' ? CommitmentEvent::Accept : CommitmentEvent::Reject);
                if ($decision === 'request_changes') {
                    ServiceCommitmentProposal::query()->create([
                        'organization_id' => $locked->organization_id,
                        'commitment_id'   => $locked->id,
                        'version'         => $proposal->version + 1,
                        'title'           => $proposal->title,
                        'terms'           => $proposal->terms,
                        'state'           => 'draft',
                    ]);
                }
            }
            $review = $this->review($locked, 'proposal', $proposal->id, $decision, $comment);
            $this->commitments->audit($locked, 'proposal_'.$decision, null, snapshot: ['proposal_id' => $proposal->id], actorType: 'client', actorEmail: $locked->client_email);

            return $review;
        });
    }

    /** @return list<ServiceReview> */
    public function reviewEvidence(ServiceCommitment $commitment, ReviewBatchData $data): array
    {
        return DB::transaction(function () use ($commitment, $data): array {
            $locked = $this->lock($commitment);
            if (!in_array($locked->state, [CommitmentState::Active->value, CommitmentState::ClosurePending->value], true)) {
                throw CommitmentException::invalidState();
            }
            $ids = array_column($data->items, 'evidence_id');
            if (count($ids) !== count(array_unique($ids))) {
                throw CommitmentException::invalidReview();
            }
            $evidence = ServiceEvidence::query()->where('organization_id', $locked->organization_id)
                ->where('commitment_id', $locked->id)->whereIn('id', $ids)->get()->keyBy('id');
            $units = ServiceDeliverable::query()->where('organization_id', $locked->organization_id)
                ->where('commitment_id', $locked->id)->whereIn('id', $evidence->pluck('deliverable_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($evidence->count() !== count($ids)) {
                throw CommitmentException::staleVersion();
            }
            if ($evidence->pluck('deliverable_id')->unique()->count() !== $evidence->count()) {
                throw CommitmentException::invalidReview();
            }
            $batchId = (string) Str::uuid7();
            $reviews = [];
            foreach ($data->items as $item) {
                $submission = $evidence->get($item['evidence_id']);
                $unit = $units->get($submission->deliverable_id);
                $decision = $item['decision'];
                $isRevoke = $decision === 'revoke';
                if ($unit === null || ($isRevoke ? $unit->accepted_evidence_id !== $submission->id : $unit->current_evidence_id !== $submission->id)) {
                    throw CommitmentException::staleVersion();
                }
                $event = match ($decision) {
                    'accept'          => ReviewEvent::Accept,
                    'request_changes' => ReviewEvent::RequestChanges,
                    'reject'          => ReviewEvent::Reject,
                    'revoke'          => ReviewEvent::Revoke,
                    default           => throw CommitmentException::invalidReview(),
                };
                $reviewState = $isRevoke ? ReviewState::Accepted : ReviewState::from($unit->validation_state);
                $result = ReviewMachine::define()->actor($reviewState)
                    ->trigger($event, fn () => CommitmentException::invalidReview());
                $previousValidationState = $unit->validation_state;
                $hasNewerPendingEvidence = $isRevoke
                    && $unit->current_evidence_id !== $submission->id
                    && $unit->validation_state === ReviewState::Pending->value;
                $unit->update([
                    'validation_state'     => $hasNewerPendingEvidence ? ReviewState::Pending->value : StateMachineValue::normalize($result->to),
                    'accepted_evidence_id' => match ($decision) {
                        'accept' => $submission->id,
                        'revoke' => null,
                        default  => $unit->accepted_evidence_id,
                    },
                ]);
                $reviews[] = $this->review($locked, 'evidence', $submission->id, $decision, $item['comment'] ?? null, $batchId);
                $this->commitments->audit($locked, 'evidence_'.$decision, null, $unit->id,
                    from: $previousValidationState,
                    to: $unit->validation_state,
                    snapshot: ['evidence_id' => $submission->id, 'batch_id' => $batchId],
                    actorType: 'client', actorEmail: $locked->client_email);
            }

            return $reviews;
        });
    }

    public function reviewLifecycle(ServiceCommitment $commitment, string $requestId, string $kind, string $decision): ServiceReview
    {
        return DB::transaction(function () use ($commitment, $requestId, $kind, $decision): ServiceReview {
            $locked = $this->lock($commitment);
            $expectedState = $kind === 'closure' ? CommitmentState::ClosurePending : CommitmentState::ReopeningPending;
            if ($locked->state !== $expectedState->value || !in_array($decision, ['accept', 'reject'], true)) {
                throw CommitmentException::invalidReview();
            }
            if ($locked->pending_lifecycle_request_id !== $requestId) {
                throw CommitmentException::staleVersion();
            }
            $request = ServiceAuditEvent::query()->where('organization_id', $locked->organization_id)
                ->where('commitment_id', $locked->id)->where('event_type', $kind.'_requested')
                ->whereKey($requestId)->firstOrFail();
            $event = match ($kind) {
                'closure'   => $decision === 'accept' ? CommitmentEvent::ConfirmClosure : CommitmentEvent::RejectClosure,
                'reopening' => $decision === 'accept' ? CommitmentEvent::ConfirmReopening : CommitmentEvent::RejectReopening,
                default     => throw CommitmentException::invalidReview(),
            };
            $this->transition($locked, $event);
            $locked->update(['pending_lifecycle_request_id' => null]);
            $review = $this->review($locked, $kind, $request->id, $decision);
            $this->commitments->audit($locked, $kind.'_'.$decision, null, snapshot: ['request_id' => $request->id], actorType: 'client', actorEmail: $locked->client_email);

            return $review;
        });
    }

    /** @return array<string, mixed> */
    public function report(ServiceCommitment $commitment): array
    {
        $units = ServiceDeliverable::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->orderBy('created_at')->get();
        $accepted = ServiceEvidence::query()->where('organization_id', $commitment->organization_id)
            ->whereIn('id', $units->pluck('accepted_evidence_id')->filter()->all())->get()->keyBy('id');

        return [
            'commitment_id' => $commitment->id,
            'state'         => $commitment->state,
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

    private function lock(ServiceCommitment $commitment): ServiceCommitment
    {
        return ServiceCommitment::query()->where('organization_id', $commitment->organization_id)
            ->whereKey($commitment->id)->lockForUpdate()->firstOrFail();
    }

    private function transition(ServiceCommitment $commitment, CommitmentEvent $event): void
    {
        $result = CommitmentMachine::define()->actor(CommitmentState::from($commitment->state))
            ->trigger($event, fn () => CommitmentException::invalidState());
        $commitment->update(['state' => StateMachineValue::normalize($result->to)]);
        $this->commitments->audit(
            $commitment,
            $event->value,
            null,
            from: StateMachineValue::normalize($result->from),
            to: StateMachineValue::normalize($result->to),
            actorType: 'client',
            actorEmail: $commitment->client_email,
        );
    }

    private function review(ServiceCommitment $commitment, string $subjectType, string $subjectId, string $decision, ?string $comment = null, ?string $batchId = null): ServiceReview
    {
        return ServiceReview::query()->create([
            'organization_id' => $commitment->organization_id,
            'commitment_id'   => $commitment->id,
            'batch_id'        => $batchId,
            'subject_type'    => $subjectType,
            'subject_id'      => $subjectId,
            'decision'        => $decision,
            'comment'         => $comment,
            'actor_email'     => $commitment->client_email,
        ]);
    }
}
