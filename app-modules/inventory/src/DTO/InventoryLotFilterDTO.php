<?php

declare(strict_types=1);

namespace Lahatre\Inventory\DTO;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\DTO\LahatreDTO;

class InventoryLotFilterDTO extends LahatreDTO
{
    public ?DeductionStrategy $strategy = null;

    public ?CarbonImmutable $expiring_before = null;

    protected function casts(): array
    {
        return [
            'strategy'        => DeductionStrategy::class,
            'expiring_before' => 'immutable_datetime',
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'strategy'        => ['nullable', Rule::enum(DeductionStrategy::class)],
            'expiring_before' => ['nullable', 'date'],
        ];
    }
}
