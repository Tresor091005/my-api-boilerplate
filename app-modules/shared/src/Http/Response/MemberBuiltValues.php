<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Exceptions\ResponseSelectionException;

/**
 * BuiltValues the SelectionLoader hands to one builder: reads ResponseContext, limited to the
 * fields the builder declares in `fields:`.
 *
 * @internal
 */
final class MemberBuiltValues implements BuiltValues
{
    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  string  $builder  what is being built, for error messages
     * @param  array<int, string>  $fields  keys the builder declares in `fields:`
     */
    public function __construct(
        private readonly ResponseContext $context,
        private readonly string $resourceClass,
        private readonly string $builder,
        private readonly array $fields,
    ) {}

    public function get(Model $model, string $field): mixed
    {
        return $this->context->computedValue($model, $this->builderOf($field), $field);
    }

    private function builderOf(string $field): string
    {
        if (!in_array($field, $this->fields, true)) {
            throw ResponseSelectionException::builtFieldNotDeclared($this->resourceClass, $this->builder, $field);
        }

        return $this->resourceClass::definition()->builderOf($this->resourceClass, $field)
            ?? throw ResponseSelectionException::builtFieldNotComputed($this->resourceClass, $this->builder, $field);
    }
}
