<?php

declare(strict_types=1);

namespace Lahatre\Sale\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Lahatre\Sale\Enums\SaleMode;

/**
 * @phpstan-type SaleSettings array{default_mode: string, modes: list<array{code: string, invoicing: string, delivery: string}>}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SaleSettingsViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public SaleMode $defaultMode,
    ) {}

    /** @return SaleSettings */
    public function toArray(): array
    {
        return [
            'default_mode' => $this->defaultMode->value,
            'modes'        => array_map(fn (SaleMode $mode): array => [
                'code'      => $mode->value,
                'invoicing' => $mode->invoicing()->value,
                'delivery'  => $mode->delivery()->value,
            ], SaleMode::cases()),
        ];
    }

    /** @return SaleSettings */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
