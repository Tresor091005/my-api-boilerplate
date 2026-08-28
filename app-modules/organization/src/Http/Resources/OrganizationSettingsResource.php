<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Organization\Models\OrganizationSetting;

/** @mixin OrganizationSetting */
class OrganizationSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'enable_currencies' => $this->enable_currencies,
            'timezone'          => $this->timezone,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
