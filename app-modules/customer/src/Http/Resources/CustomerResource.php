<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Library\Http\Resources\FileAttachmentResource;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\Master\Http\Resources\ContactResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'type'                  => $this->type,
            'name'                  => $this->name,
            'identification_number' => $this->identification_number,
            'is_active'             => $this->is_active,
            'addresses'             => $this->whenLoaded(
                'addresses',
                fn ($addresses): mixed => AddressResource::collection($addresses),
            ),
            'contacts' => $this->whenLoaded(
                'contacts',
                fn ($contacts): mixed => ContactResource::collection($contacts),
            ),
            'files' => $this->whenLoaded('profilePictureFileAttachments', fn ($attachments): array => [
                'profile_picture' => $attachments->map(fn ($attachment): FileAttachmentResource => new FileAttachmentResource(
                    $attachment, 'lahatre.customer.customers.files.content', 'customer', $this->id,
                ))->all(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
