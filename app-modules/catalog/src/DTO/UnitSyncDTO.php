<?php

declare(strict_types=1);

namespace Lahatre\Catalog\DTO;

use Illuminate\Support\Collection;
use Lahatre\Shared\DTO\LahatreDTO;
use Lahatre\Shared\Rules\BulkExists;
use WendellAdriel\ValidatedDTO\Casting\CollectionCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;

class UnitSyncDTO extends LahatreDTO
{
    public string $unit_group;

    /** @var Collection<int, UnitDataDTO> */
    public Collection $units;

    protected function casts(): array
    {
        return [
            'units' => new CollectionCast(new DTOCast(UnitDataDTO::class)),
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'unit_group' => ['required', 'string'],
            'units'      => [
                'required',
                'array',
                'min:1',
                new BulkExists('catalog_units', 'id', 'id'),
            ],
        ];
    }
}
