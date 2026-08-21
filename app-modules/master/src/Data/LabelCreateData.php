<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class LabelCreateData
{
    /**
     * @param  array<string, array<int, string>>  $labelsByGroup
     */
    private function __construct(public array $labelsByGroup) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, array<int, string>> $labelsByGroup */
        $labelsByGroup = $data['labels'];

        return new self(labelsByGroup: $labelsByGroup);
    }
}
