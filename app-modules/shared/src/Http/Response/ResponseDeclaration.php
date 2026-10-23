<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Lahatre\Shared\Enums\ResponseShape;

/**
 * What an action's ApiResponse calls declare, read statically by ResponseDeclarationAnalyzer: its
 * single factory call, or only ApiResponse::noContent() (no Resource, no shape, no status).
 */
final class ResponseDeclaration
{
    /**
     * @param  class-string<ApiResource>|null  $resourceClass  null for a no-content-only action
     * @param  array<int, int>  $statuses  every status the call may answer with
     * @param  bool  $canBeEmpty  the action also calls ApiResponse::noContent(), so it may answer 204 in any mode
     */
    public function __construct(
        public readonly ?string $resourceClass,
        public readonly ?ResponseShape $shape,
        public readonly array $statuses,
        public readonly bool $canBeEmpty = false,
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
     * How the declaration reads in messages: `item(XResource::class)`, or `noContent()`.
     */
    public function describe(): string
    {
        return $this->shape === null ? 'noContent()' : "{$this->shape->factory()}({$this->resourceClass}::class)";
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
