<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Commitment\Http\Resources\ServiceCommitmentProposalResource;
use Lahatre\Commitment\Http\Resources\ServiceCommitmentResource;
use Lahatre\Commitment\Http\Resources\ServiceDeliverableCollection;
use Lahatre\Commitment\Http\Resources\ServiceEvidenceCollection;
use Lahatre\Commitment\Http\Resources\ServiceReviewCollection;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Models\ServiceEvidence;
use Lahatre\Commitment\Models\ServiceReview;
use Lahatre\Commitment\Services\GuestReviewService;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\FileAttachment;
use Symfony\Component\HttpFoundation\Response;

final readonly class GuestCommitmentController
{
    public function __construct(private GuestReviewService $reviews, private PrivateFileResponseFactory $files) {}

    public function show(Request $request, string $reference): JsonResponse
    {
        $commitment = $this->commitment($request);
        $proposals = ServiceCommitmentProposal::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->where('state', '!=', 'draft')->orderBy('version')->get();

        return response()->json([
            'commitment' => ServiceCommitmentResource::make($commitment),
            'proposals'  => ServiceCommitmentProposalResource::collection($proposals),
        ]);
    }

    public function deliverables(Request $request, string $reference): JsonResponse
    {
        $commitment = $this->commitment($request);
        $items = ServiceDeliverable::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->orderBy('created_at')->orderBy('id')->cursorPaginate(50);

        return ServiceDeliverableCollection::make($items)->response();
    }

    public function evidence(Request $request, string $reference): JsonResponse
    {
        $commitment = $this->commitment($request);
        $items = ServiceEvidence::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->orderBy('submitted_at')->orderBy('id')->cursorPaginate(50);

        return ServiceEvidenceCollection::make($items)->response();
    }

    public function reviews(Request $request, string $reference): JsonResponse
    {
        $commitment = $this->commitment($request);
        $items = ServiceReview::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->orderBy('created_at')->orderBy('id')->cursorPaginate(50);

        return ServiceReviewCollection::make($items)->response();
    }

    public function attachments(Request $request, string $reference, string $evidence): JsonResponse
    {
        $commitment = $this->commitment($request);
        $proof = ServiceEvidence::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->whereKey($evidence)->firstOrFail();
        $attachments = FileAttachment::query()->where('organization_id', $commitment->organization_id)
            ->where('attachable_type', $proof->getMorphClass())->where('attachable_id', $proof->id)
            ->where('slot', 'evidence')->orderBy('created_at')->get();
        $files = File::query()->where('organization_id', $commitment->organization_id)
            ->whereIn('id', $attachments->pluck('file_id'))->get()->keyBy('id');

        return response()->json(['data' => $attachments->map(static function (FileAttachment $attachment) use ($files): array {
            $file = $files->get($attachment->file_id);

            return ['id' => $attachment->id, 'name' => $file?->original_name, 'mime_type' => $file?->mime_type, 'size' => $file?->size];
        })->values()]);
    }

    public function report(Request $request, string $reference): JsonResponse
    {
        return response()->json($this->reviews->report($this->commitment($request)));
    }

    public function content(Request $request, string $reference, string $evidence, string $attachment): Response
    {
        $commitment = $this->commitment($request);
        $proof = ServiceEvidence::query()->where('organization_id', $commitment->organization_id)
            ->where('commitment_id', $commitment->id)->whereKey($evidence)->firstOrFail();
        $file = FileAttachment::query()->where('organization_id', $commitment->organization_id)
            ->where('attachable_type', $proof->getMorphClass())->where('attachable_id', $proof->id)
            ->where('slot', 'evidence')->whereKey($attachment)->firstOrFail();
        $linkedFile = File::query()->where('organization_id', $commitment->organization_id)
            ->whereKey($file->file_id)->firstOrFail();

        return $this->files->make($request, $linkedFile);
    }

    private function commitment(Request $request): ServiceCommitment
    {
        return $request->attributes->get('guestCommitment');
    }
}
