<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class TagCreateData
{
    /**
     * @param  array<string, array<int, string>>  $tagsByType
     */
    private function __construct(public array $tagsByType) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, array<int, string>> $tagsByType */
        $tagsByType = $data['tags'];

        return new self(tagsByType: $tagsByType);
    }
}
