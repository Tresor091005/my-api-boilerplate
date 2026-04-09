<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Shared\DTO\LahatreDTO;

class InventoryMovementFilterDTO extends LahatreDTO
{
    public int $page;

    public int $per_page;

    public ?CarbonImmutable $from = null;

    public ?CarbonImmutable $to = null;

    public ?MovementType $movement_type = null;

    public ?string $reference_type = null;

    public ?string $reference_id = null;

    protected function casts(): array
    {
        return [
            'page'          => 'int',
            'per_page'      => 'int',
            'from'          => 'immutable_datetime',
            'to'            => 'immutable_datetime',
            'movement_type' => MovementType::class,
        ];
    }

    protected function defaults(): array
    {
        return [
            'page'     => 1,
            'per_page' => 50,
        ];
    }

    protected function rules(): array
    {
        return [
            'page'           => ['integer', 'min:1'],
            'per_page'       => ['integer', 'min:1', 'max:100'],
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'movement_type'  => ['nullable', Rule::enum(MovementType::class)],
            'reference_type' => ['nullable', 'string'],
            'reference_id'   => ['nullable', 'string'],
        ];
    }
}
