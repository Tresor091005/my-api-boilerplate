<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Contact;

/** @mixin Contact */
class ContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'value'      => $this->value,
            'is_primary' => $this->is_primary,
        ];
    }
}
