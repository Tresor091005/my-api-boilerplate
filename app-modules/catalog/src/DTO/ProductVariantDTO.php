<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Collection;
use Lahatre\Shared\DTO\LahatreDTO;
use Lahatre\Shared\Rules\BulkExists;

class ProductVariantDTO extends LahatreDTO
{
    /** @var Collection<int, ProductVariantDataDTO> */
    public Collection $variants;

    protected function casts(): array
    {
        return [
            'variants' => 'collection:'.ProductVariantDataDTO::class,
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'variants' => [
                'required',
                'array',
                'min:1',
                new BulkExists('master_unit_groups', 'id', 'unit_group_id', 'uuid', true),
            ],
        ];
    }
}
