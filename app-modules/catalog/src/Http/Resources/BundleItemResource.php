<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Contracts\MasterInterface;

/** @mixin BundleItem */
class BundleItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'item_type'  => $this->item_type,
            'item_id'    => $this->item_id,
            'quantity'   => $this->resolveQuantity(),
            'unit_code'  => $this->display_unit_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'component'  => $this->whenLoaded(
                'component',
                fn (mixed $component): JsonResource|MissingValue => $this->componentResource($component),
            ),
        ];
    }

    private function resolveQuantity(): int
    {
        return (int) app(MasterInterface::class)
            ->convertUnitFromBase((string) $this->quantity, $this->display_unit_code)['amount'];
    }

    protected function componentResource(mixed $component): JsonResource|MissingValue
    {
        if (!$component instanceof Model) {
            return new MissingValue;
        }

        return match ($component::class) {
            ProductVariant::class => ProductVariantResource::make($component),
            default               => new MissingValue,
        };
    }
}
