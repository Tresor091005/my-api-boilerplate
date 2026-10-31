<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Billing\Registries\FeatureDefinition;

/**
 * @property FeatureDefinition $resource
 */
class FeatureResource extends JsonResource
{
    /** @return array{key: string, type: string|null, name: string, options: list<string>|null, window_seconds: int|null} */
    public function toArray(Request $request): array
    {
        return [
            'key'            => $this->resource->key,
            'type'           => $this->resource->type?->value,
            'name'           => __($this->resource->translationKey()),
            'options'        => $this->resource->options,
            'window_seconds' => $this->resource->window === null ? null : (int) $this->resource->window->totalSeconds,
        ];
    }
}
