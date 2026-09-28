<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service as CatalogService;
use Lahatre\Catalog\Services\ServiceService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Commitment\Data\CommitmentData;
use Lahatre\Commitment\Data\DeliverableData;
use Lahatre\Commitment\Data\EvidenceData;
use Lahatre\Commitment\Data\ReviewBatchData;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Commitment\Enums\ReviewState;
use Lahatre\Commitment\Exceptions\CommitmentException;
use Lahatre\Commitment\Http\Requests\DeliverableRequest;
use Lahatre\Commitment\Http\Requests\EvidenceCreateRequest;
use Lahatre\Commitment\Models\GuestAccessChallenge;
use Lahatre\Commitment\Models\ServiceAuditEvent;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Policies\ServiceCommitmentPolicy;
use Lahatre\Commitment\Services\CommitmentService;
use Lahatre\Commitment\Services\GuestAccessService;
use Lahatre\Commitment\Services\GuestReviewService;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $group = UnitGroup::factory()->create(['organization_id' => null]);
    $service = app(ServiceService::class)->create(ServiceData::fromArray([
        'name'                  => 'Home tutoring',
        'unit_group_id'         => $group->id,
        'is_active'             => true,
        'deliverable_templates' => [['name' => 'Lesson report']],
    ]));
    $customer = Customer::factory()->create(['organization_id' => $this->organizationId]);
    $this->commitments = app(CommitmentService::class);
    $this->reviews = app(GuestReviewService::class);
    $this->commitment = $this->commitments->create(CommitmentData::fromArray([
        'service_id'   => $service->id,
        'customer_id'  => $customer->id,
        'client_email' => 'parent@example.test',
    ]), null);
});

it('keeps an empty draft and records only client accepted work in the confirmed report', function (): void {
    expect(ServiceDeliverable::query()->where('commitment_id', $this->commitment->id)->count())->toBe(0);

    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'accept', null);
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::Active->value);

    $unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray([
        'title' => 'Math lesson on fractions',
    ]), null);
    $evidence = $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome'   => 'performed',
        'narrative' => 'Fractions and homework reviewed.',
    ]), null);
    expect($this->commitments->report($this->commitment)['confirmed'])->toBe([]);

    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $evidence->id, 'decision' => 'accept']],
    ]));
    expect($this->commitments->report($this->commitment)['confirmed'])->toHaveCount(1)
        ->and($unit->fresh()->validation_state)->toBe(ReviewState::Accepted->value);
    $this->commitments->updateDeliverable($this->commitment, $unit, DeliverableData::fromArray([
        'title' => 'Changed after acceptance',
    ]), null);
    expect($this->commitments->report($this->commitment)['confirmed'][0]['title'])
        ->toBe('Math lesson on fractions');

    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $evidence->id, 'decision' => 'revoke', 'comment' => 'Disputed later']],
    ]));
    expect($this->commitments->report($this->commitment)['confirmed'])->toBe([])
        ->and($unit->fresh()->validation_state)->toBe(ReviewState::Disputed->value);
});

it('closes with pending work and requires two decisions to reopen', function (): void {
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'accept', null);
    $unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray(['title' => 'Session one']), null);
    $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome'   => 'performed',
        'narrative' => 'Lesson completed.',
    ]), null);

    $closure = $this->commitments->requestClosure($this->commitment, null);
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::ClosurePending->value);
    $this->reviews->reviewLifecycle($this->commitment, $closure->id, 'closure', 'accept');
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::Closed->value)
        ->and($this->reviews->report($this->commitment)['pending_count'])->toBe(1);

    $reopening = $this->commitments->requestReopening($this->commitment, null);
    $this->reviews->reviewLifecycle($this->commitment, $reopening->id, 'reopening', 'accept');
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::Active->value)
        ->and(ServiceAuditEvent::query()->where('commitment_id', $this->commitment->id)->count())->toBeGreaterThan(5);
});

it('preserves omitted deliverable fields and clears explicit nulls', function (): void {
    $unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray([
        'title'             => 'Lesson',
        'description'       => 'Original description',
        'scheduled_at'      => '2026-10-01T10:00:00Z',
        'quantity'          => '2.5000',
        'display_unit_code' => 'hour',
    ]), null);

    $this->commitments->updateDeliverable($this->commitment, $unit, DeliverableData::fromArray(
        ['title' => 'Updated lesson'],
        missingFields: ['title', 'description', 'scheduled_at', 'quantity', 'display_unit_code'],
    ), null);
    expect($unit->fresh()->description)->toBe('Original description')
        ->and($unit->fresh()->quantity)->toBe('2.5000')
        ->and($unit->fresh()->display_unit_code)->toBe('hour')
        ->and($unit->fresh()->scheduled_at)->not->toBeNull();

    $this->commitments->updateDeliverable($this->commitment, $unit, DeliverableData::fromArray(
        ['description' => null, 'scheduled_at' => null],
        missingFields: ['title', 'description', 'scheduled_at', 'quantity', 'display_unit_code'],
    ), null);
    expect($unit->fresh()->title)->toBe('Updated lesson')
        ->and($unit->fresh()->description)->toBeNull()
        ->and($unit->fresh()->scheduled_at)->toBeNull();
});

it('uses the active lifecycle request even when two requests share a timestamp', function (): void {
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'accept', null);
    $this->travelTo(now()->startOfSecond());
    $oldRequest = $this->commitments->requestClosure($this->commitment, null);
    $this->reviews->reviewLifecycle($this->commitment, $oldRequest->id, 'closure', 'reject');
    $currentRequest = $this->commitments->requestClosure($this->commitment, null);
    expect($oldRequest->created_at->equalTo($currentRequest->created_at))->toBeTrue()
        ->and($this->commitment->fresh()->pending_lifecycle_request_id)->toBe($currentRequest->id);
    expect(fn () => $this->reviews->reviewLifecycle($this->commitment, $oldRequest->id, 'closure', 'accept'))
        ->toThrow(CommitmentException::class);
    $this->reviews->reviewLifecycle($this->commitment, $currentRequest->id, 'closure', 'accept');
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::Closed->value)
        ->and($this->commitment->fresh()->pending_lifecycle_request_id)->toBeNull();
});

it('rejects quantities outside the decimal column precision', function (): void {
    $deliverableRequest = DeliverableRequest::create('/v1/commitment/service-commitments/1/deliverables', 'POST');
    $evidenceRequest = EvidenceCreateRequest::create('/v1/commitment/service-commitments/1/deliverables/1/evidence', 'POST');
    foreach ([$deliverableRequest, $evidenceRequest] as $request) {
        $base = $request instanceof DeliverableRequest
            ? ['title' => 'Lesson']
            : ['outcome' => 'performed'];
        expect(Validator::make([...$base, 'quantity' => '100000000000000', 'display_unit_code' => 'hour'], $request->rules())->errors()->has('quantity'))->toBeTrue()
            ->and(Validator::make([...$base, 'quantity' => '1.00001', 'display_unit_code' => 'hour'], $request->rules())->errors()->has('quantity'))->toBeTrue()
            ->and(Validator::make([...$base, 'quantity' => '99999999999999.9999', 'display_unit_code' => 'hour'], $request->rules())->passes())->toBeTrue();
    }
});

it('preserves review of a correction when an older acceptance is revoked', function (): void {
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'accept', null);
    $unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray(['title' => 'Lesson']), null);
    $first = $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome' => 'performed', 'narrative' => 'Initial report',
    ]), null);
    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $first->id, 'decision' => 'accept']],
    ]));
    $correction = $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome' => 'performed', 'narrative' => 'Corrected report',
    ]), null);
    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $first->id, 'decision' => 'revoke']],
    ]));
    expect($unit->fresh()->validation_state)->toBe(ReviewState::Pending->value)
        ->and($this->commitments->report($this->commitment)['confirmed'])->toBe([]);
    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $correction->id, 'decision' => 'accept']],
    ]));
    expect($this->commitments->report($this->commitment)['confirmed'])->toHaveCount(1);
});

it('replaces a pending evidence version before client review', function (): void {
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'accept', null);
    $unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray(['title' => 'Lesson']), null);
    $first = $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome'      => 'rescheduled',
        'reason'       => 'Student unavailable',
        'scheduled_at' => '2026-10-02T10:00:00Z',
    ]), null);
    $current = $this->commitments->submitEvidence($this->commitment, $unit, EvidenceData::fromArray([
        'outcome'      => 'performed',
        'narrative'    => 'Lesson delivered on the new date',
        'performed_at' => '2026-10-02T10:00:00Z',
    ]), null);

    expect($current->version)->toBe(2)
        ->and($unit->fresh()->current_evidence_id)->toBe($current->id)
        ->and($unit->fresh()->validation_state)->toBe(ReviewState::Pending->value);
    expect(fn () => $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $first->id, 'decision' => 'accept']],
    ])))->toThrow(CommitmentException::class);
    $this->reviews->reviewEvidence($this->commitment, ReviewBatchData::fromArray([
        'items' => [['evidence_id' => $current->id, 'decision' => 'accept']],
    ]));
    expect($unit->fresh()->accepted_evidence_id)->toBe($current->id);
});

it('invalidates the earlier email code when another is requested', function (): void {
    Mail::fake();
    $access = app(GuestAccessService::class);
    $access->sendCode($this->commitment->public_reference);
    $first = GuestAccessChallenge::query()->where('commitment_id', $this->commitment->id)->firstOrFail();
    $access->sendCode($this->commitment->public_reference);

    expect($first->fresh()->consumed_at)->not->toBeNull()
        ->and(GuestAccessChallenge::query()->where('commitment_id', $this->commitment->id)
            ->whereNull('consumed_at')->count())->toBe(1);
});

it('grants a short lived email code session scoped to one engagement', function (): void {
    Mail::fake();
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $reference = $this->commitment->public_reference;

    $this->postJson('/v1/commitment/guest/access-challenges', ['reference' => $reference])->assertNoContent();
    $challenge = GuestAccessChallenge::query()
        ->where('commitment_id', $this->commitment->id)->firstOrFail();
    $challenge->update(['code_hash' => Hash::make('123456')]);

    $this->postJson('/v1/commitment/guest/access-sessions', ['reference' => $reference, 'code' => '000000'])
        ->assertStatus(422);
    expect($challenge->fresh()->attempts)->toBe(1);

    $token = $this->postJson('/v1/commitment/guest/access-sessions', ['reference' => $reference, 'code' => 123456])
        ->assertCreated()->json('token');
    $this->getJson('/v1/commitment/guest/service-commitments/'.$reference)->assertUnauthorized();
    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/'.$reference)
        ->assertOk()->assertJsonPath('commitment.id', $this->commitment->id)
        ->assertJsonCount(1, 'proposals');
    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/another-reference')
        ->assertUnauthorized();
    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/'.$reference.'/deliverables')
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.per_page', 50);
    $this->withToken($token)->postJson('/v1/commitment/guest/service-commitments/'.$reference.'/proposal-reviews', [
        'proposal_id' => $proposal->id,
        'decision'    => 'accept',
    ])->assertNoContent();
    expect($this->commitment->fresh()->state)->toBe(CommitmentState::Active->value);
    $closure = $this->commitments->requestClosure($this->commitment, null);
    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/'.$reference)
        ->assertOk()->assertJsonPath('commitment.pending_lifecycle_request_id', $closure->id);
    $this->withToken($token)->postJson('/v1/commitment/guest/service-commitments/'.$reference.'/closure-reviews', [
        'request_id' => $closure->id,
        'decision'   => 'accept',
    ])->assertNoContent();
    $reopening = $this->commitments->requestReopening($this->commitment, null);
    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/'.$reference)
        ->assertOk()->assertJsonPath('commitment.pending_lifecycle_request_id', $reopening->id);
    $this->withToken($token)->postJson('/v1/commitment/guest/service-commitments/'.$reference.'/reopening-reviews', [
        'request_id' => $reopening->id,
        'decision'   => 'accept',
    ])->assertNoContent();
});

it('keeps revised draft proposals private from the guest', function (): void {
    $proposal = $this->commitments->submitProposal($this->commitment, null, null, null);
    $this->reviews->reviewProposal($this->commitment, $proposal->id, 'request_changes', 'Clarify timing');
    Mail::fake();
    $reference = $this->commitment->public_reference;
    $this->postJson('/v1/commitment/guest/access-challenges', ['reference' => $reference])->assertNoContent();
    $challenge = GuestAccessChallenge::query()->where('commitment_id', $this->commitment->id)->firstOrFail();
    $challenge->update(['code_hash' => Hash::make('123456')]);
    $token = $this->postJson('/v1/commitment/guest/access-sessions', ['reference' => $reference, 'code' => '123456'])
        ->assertCreated()->json('token');

    $this->withToken($token)->getJson('/v1/commitment/guest/service-commitments/'.$reference)
        ->assertOk()->assertJsonCount(1, 'proposals');
});

it('registers the provider policy and rejects cross tenant records', function (): void {
    expect(Gate::getPolicyFor(ServiceCommitment::class))->toBeInstanceOf(ServiceCommitmentPolicy::class);
    $catalogItem = CatalogItem::factory()->service()->create(['organization_id' => $this->otherOrganizationId]);
    $service = CatalogService::factory()->forCatalogItem($catalogItem)->create();
    $customer = Customer::factory()->create(['organization_id' => $this->otherOrganizationId]);
    $other = ServiceCommitment::query()->create([
        'organization_id'  => $this->otherOrganizationId,
        'service_id'       => $service->id,
        'customer_id'      => $customer->id,
        'client_email'     => 'other@example.test',
        'public_reference' => Str::upper(Str::random(16)),
        'state'            => CommitmentState::Draft->value,
    ]);

    $this->commitments->retrieve($other->id);
})->throws(ModelNotFoundException::class);
