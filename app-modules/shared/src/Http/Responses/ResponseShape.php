<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ResponseShape
{
    /**
     * @param  list<string>  $requiredLoads
     * @param  array<string, ResponseInclude>  $includes
     */
    public function __construct(
        public string $name,
        public array $requiredLoads = [],
        public array $includes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(string $name, array $definition): self
    {
        if (array_key_exists('fields', $definition)) {
            throw new InvalidArgumentException(__('shared::exceptions.response_shape_fields_unsupported', [
                'shape' => $name,
            ]));
        }

        $includes = [];
        foreach ($definition['includes'] ?? [] as $includeName => $includeDefinition) {
            $includes[$includeName] = ResponseInclude::fromArray($includeName, $includeDefinition);
        }

        return new self(
            name: $name,
            requiredLoads: array_values($definition['required_loads'] ?? []),
            includes: $includes,
        );
    }

    /**
     * @param  list<string>  $requestedIncludes
     * @return list<string>
     */
    public function validateIncludes(array $requestedIncludes): array
    {
        $invalidIncludes = array_values(array_diff($requestedIncludes, array_keys($this->includes)));

        if ($invalidIncludes !== []) {
            throw ValidationException::withMessages([
                'include' => [__('shared::validation.response_includes_not_allowed', [
                    'shape'    => $this->name,
                    'includes' => implode(', ', $invalidIncludes),
                ])],
            ]);
        }

        return array_values(array_unique($requestedIncludes));
    }

    /** @return list<string> */
    public function relationsToLoad(array $requestedIncludes): array
    {
        return array_values(array_unique([
            ...$this->requiredLoads,
            ...array_merge(...array_map(
                fn (string $include): array => $this->includes[$include]->loads,
                $requestedIncludes,
            )),
        ]));
    }
}
