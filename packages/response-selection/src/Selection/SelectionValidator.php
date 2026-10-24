<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Selection;

use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\ApiResource;

/**
 * Rejects a requested field or include that the Resource does not declare, before the controller runs.
 * When an include can render several Resources, a name is accepted if any of them declares it.
 */
final class SelectionValidator
{
    /**
     * @param  class-string<ApiResource>  $resourceClass
     *
     * @throws ValidationException
     */
    public function validate(SelectionNode $root, string $resourceClass): void
    {
        $this->visit($root, [$resourceClass]);
    }

    /**
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     */
    private function visit(SelectionNode $node, array $resourceClasses): void
    {
        $definitions = array_map(fn (string $class) => $class::definition(), $resourceClasses);

        foreach ($node->fields() ?? [] as $key) {
            if ($key === 'id') {
                continue;
            }

            foreach ($definitions as $definition) {
                if ($definition->hasField($key)) {
                    continue 2;
                }
            }

            throw ValidationException::withMessages([
                $node->isRoot() ? 'fields' : 'include' => [__('response-selection::validation.unknown_field', [
                    'path' => $node->isRoot() ? $key : "{$node->path}.{$key}",
                ])],
            ]);
        }

        foreach ($node->children() as $name => $child) {
            $children = [];

            foreach ($definitions as $definition) {
                if ($definition->includeDefinition($name) !== null) {
                    array_push($children, ...$definition->includeResourceClasses($name));
                }
            }

            if ($children === []) {
                throw ValidationException::withMessages([
                    'include' => [__('response-selection::validation.unknown_include', ['path' => $child->path])],
                ]);
            }

            $this->visit($child, array_values(array_unique($children)));
        }
    }
}
