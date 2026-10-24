<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;

/**
 * Gives a builder the computed values it declared it needs, and nothing else.
 *
 * @internal
 */
final class MemberBuiltValues implements BuiltValues
{
    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  string  $builder  who reads the values (a class, or Resource::field for a closure), named in errors
     * @param  array<int, string>  $fields  fields the builder may read
     */
    public function __construct(
        private readonly ResponseContext $context,
        private readonly string $resourceClass,
        private readonly string $builder,
        private readonly array $fields,
    ) {}

    public function get(Model $model, string $field): mixed
    {
        return $this->context->computedValue($model, $this->slotOf($field));
    }

    private function slotOf(string $field): string
    {
        if (!in_array($field, $this->fields, true)) {
            throw ResponseSelectionException::builtFieldNotDeclared($this->resourceClass, $this->builder, $field);
        }

        return $this->resourceClass::definition()->slotOf($this->resourceClass, $field)
            ?? throw ResponseSelectionException::builtFieldNotComputed($this->resourceClass, $this->builder, $field);
    }
}
