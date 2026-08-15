<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

use Illuminate\Validation\ValidationException;

final readonly class ResponseContract
{
    /**
     * @param  array<string, ResponseShape>  $shapes
     */
    public function __construct(
        public ?ResponseMode $defaultMode,
        public array $shapes = [],
        public ?string $defaultShape = null,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        $shapes = [];
        foreach ($definition['shapes'] ?? [] as $name => $shape) {
            $shapes[$name] = ResponseShape::fromArray($name, $shape);
        }

        return new self(
            defaultMode: isset($definition['default_mode'])
                ? ResponseMode::from($definition['default_mode'])
                : null,
            shapes: $shapes,
            defaultShape: $definition['default_shape'] ?? null,
        );
    }

    public function resolveMode(?string $requestedMode, string $httpMethod): ResponseMode
    {
        $mode = $requestedMode === null
            ? ($this->defaultMode ?? ResponseMode::defaultForHttpMethod($httpMethod))
            : ResponseMode::tryFrom($requestedMode);

        if ($mode === null) {
            throw ValidationException::withMessages([
                'response' => [__('shared::validation.response_mode_invalid')],
            ]);
        }

        if (strtoupper($httpMethod) === 'DELETE' && $mode === ResponseMode::Resource) {
            throw ValidationException::withMessages([
                'response' => [__('shared::validation.response_delete_forbidden')],
            ]);
        }

        if ($requestedMode !== null
            && $this->defaultMode === null
            && strtoupper($httpMethod) === 'GET'
            && $mode === ResponseMode::None
        ) {
            throw ValidationException::withMessages([
                'response' => [__('shared::validation.response_resource_required')],
            ]);
        }

        return $mode;
    }

    public function resolveShape(?string $requestedShape): ?ResponseShape
    {
        if ($this->shapes === []) {
            if ($requestedShape !== null) {
                throw ValidationException::withMessages([
                    'shape' => [__('shared::validation.response_shapes_unsupported')],
                ]);
            }

            return null;
        }

        $shapeName = $requestedShape ?? $this->defaultShape;
        $shape = $shapeName === null ? null : ($this->shapes[$shapeName] ?? null);

        if ($shape === null) {
            throw ValidationException::withMessages([
                'shape' => [__('shared::validation.response_shape_invalid')],
            ]);
        }

        return $shape;
    }
}
