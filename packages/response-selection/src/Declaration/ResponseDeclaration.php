<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Declaration;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Enums\ResponseShape;

/**
 * What a controller action answers with: a Resource, its shape and its statuses, or no content at all.
 */
final readonly class ResponseDeclaration
{
    /**
     * @param  class-string<ApiResource>|null  $resourceClass  null when the action never answers with a body
     * @param  array<int, int>  $statuses  every status the action may answer with
     * @param  bool  $canBeEmpty  the action may also answer 204 with no body
     */
    public function __construct(
        public ?string $resourceClass,
        public ?ResponseShape $shape,
        public array $statuses,
        public bool $canBeEmpty = false,
    ) {}

    public static function noContentOnly(): self
    {
        return new self(null, null, [], true);
    }

    public function isNoContentOnly(): bool
    {
        return $this->resourceClass === null;
    }

    /**
     * How the declaration reads in error messages, such as `item(XResource::class)`.
     */
    public function describe(): string
    {
        return $this->shape === null ? 'noContent()' : "{$this->shape->value}({$this->resourceClass}::class)";
    }

    /**
     * @return array{resource: class-string<ApiResource>|null, shape: string|null, statuses: array<int, int>, can_be_empty: bool}
     */
    public function toArray(): array
    {
        return [
            'resource'     => $this->resourceClass,
            'shape'        => $this->shape?->value,
            'statuses'     => $this->statuses,
            'can_be_empty' => $this->canBeEmpty,
        ];
    }

    /**
     * @param  array{resource: class-string<ApiResource>|null, shape: string|null, statuses: array<int, int>, can_be_empty: bool}  $declaration
     */
    public static function fromArray(array $declaration): self
    {
        return new self(
            $declaration['resource'],
            $declaration['shape'] === null ? null : ResponseShape::from($declaration['shape']),
            $declaration['statuses'],
            $declaration['can_be_empty'],
        );
    }
}
