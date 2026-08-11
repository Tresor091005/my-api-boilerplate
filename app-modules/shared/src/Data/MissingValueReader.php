<?php

declare(strict_types=1);

namespace Lahatre\Shared\Data;

final readonly class MissingValueReader
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields  Fields of this array only; nested data has its own scope.
     */
    private function __construct(
        private array $data,
        private array $missingFields,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        return new self($data, $missingFields);
    }

    public function get(
        string $field,
        mixed $default = MissingValue::Instance,
    ): mixed {
        return MissingValue::fromArray(
            $this->data,
            $field,
            $this->missingFields,
            $default,
        );
    }
}
