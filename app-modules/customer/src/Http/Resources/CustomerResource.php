<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\Master\Http\Resources\ContactResource;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    use RendersResponseIncludes;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'type'                  => $this->type,
            'name'                  => $this->name,
            'identification_number' => $this->identification_number,
            'is_active'             => $this->is_active,
            'addresses'             => $this->includeWhenRequestedAndLoaded(
                include: 'addresses',
                relation: 'addresses',
                resolver: fn ($addresses): mixed => AddressResource::collection($addresses),
            ),
            'contacts' => $this->includeWhenRequestedAndLoaded(
                include: 'contacts',
                relation: 'contacts',
                resolver: fn ($contacts): mixed => ContactResource::collection($contacts),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
