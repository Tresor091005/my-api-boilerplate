<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Commitment\Data\ReviewBatchData;
use Lahatre\Commitment\Http\Requests\EvidenceReviewRequest;
use Lahatre\Commitment\Http\Requests\LifecycleReviewRequest;
use Lahatre\Commitment\Http\Requests\ProposalReviewRequest;
use Lahatre\Commitment\Http\Resources\ServiceReviewCollection;
use Lahatre\Commitment\Http\Resources\ServiceReviewResource;
use Lahatre\Commitment\Services\GuestReviewService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class GuestReviewController
{
    public function __construct(private GuestReviewService $reviews, private ResponseResponder $responder) {}

    public function proposal(ProposalReviewRequest $request, string $reference): JsonResponse|Response
    {
        $review = $this->reviews->reviewProposal(
            $request->attributes->get('guestCommitment'),
            $request->validated('proposal_id'),
            $request->validated('decision'),
            $request->validated('comment'),
        );

        return $this->responder->respond(fn () => ServiceReviewResource::make($review), 201);
    }

    public function evidence(EvidenceReviewRequest $request, string $reference): JsonResponse|Response
    {
        $reviews = $this->reviews->reviewEvidence(
            $request->attributes->get('guestCommitment'),
            ReviewBatchData::fromArray($request->validated()),
        );

        return $this->responder->respond(fn () => ServiceReviewCollection::make($reviews), 201);
    }

    public function closure(LifecycleReviewRequest $request, string $reference): JsonResponse|Response
    {
        $review = $this->reviews->reviewLifecycle(
            $request->attributes->get('guestCommitment'),
            $request->validated('request_id'),
            'closure',
            $request->validated('decision'),
        );

        return $this->responder->respond(fn () => ServiceReviewResource::make($review), 201);
    }

    public function reopening(LifecycleReviewRequest $request, string $reference): JsonResponse|Response
    {
        $review = $this->reviews->reviewLifecycle(
            $request->attributes->get('guestCommitment'),
            $request->validated('request_id'),
            'reopening',
            $request->validated('decision'),
        );

        return $this->responder->respond(fn () => ServiceReviewResource::make($review), 201);
    }
}
