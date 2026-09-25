<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Http\Resources\CustomerResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Customer\Services\CustomerFileService;
use Lahatre\Library\Http\Requests\SingleFileRequest;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerFileController
{
    public function __construct(
        private CustomerFileService $files,
        private PrivateFileResponseFactory $fileResponses,
        private ResponseResponder $responses,
    ) {}

    public function updateProfilePicture(SingleFileRequest $request, Customer $customer): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        $fileId = $request->validated('file_id');
        $customer = $this->files->setProfilePicture($customer, $fileId === null ? [] : [$fileId]);

        return $this->responses->respond(fn (): JsonResource => CustomerResource::make($customer));
    }

    public function content(Request $request, Customer $customer, string $attachment): Response
    {
        Gate::authorize('retrieve', $customer);

        return $this->fileResponses->make($request, $this->files->find($customer, $attachment)->linkedFile());
    }
}
