<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Exceptions;

use LogicException;

/**
 * Thrown when the package is misused while building a response. It signals a coding mistake, so the
 * client gets a server error.
 */
final class ResponseSelectionException extends LogicException
{
    public static function itemReceivedPage(): self
    {
        return self::make(__('response-selection::selection.item_received_page'));
    }

    public static function itemReceivedList(): self
    {
        return self::make(__('response-selection::selection.item_received_list'));
    }

    public static function itemRequiresResult(): self
    {
        return self::make(__('response-selection::selection.item_requires_result'));
    }

    public static function listHasStringKeys(string $factory): self
    {
        return self::make(
            __('response-selection::selection.list_has_string_keys', ['factory' => $factory]),
            ['factory' => $factory],
        );
    }

    public static function resourceGivenAsResult(string $factory): self
    {
        return self::make(
            __('response-selection::selection.resource_given_as_result', ['factory' => $factory]),
            ['factory' => $factory],
        );
    }

    public static function notAModel(string $resource, string $type): self
    {
        return self::make(
            __('response-selection::selection.not_a_model', ['resource' => $resource, 'type' => $type]),
            ['resource' => $resource, 'type' => $type],
        );
    }

    public static function declarationMissing(string $action): self
    {
        return self::make(
            __('response-selection::selection.declaration_missing', ['action' => $action]),
            ['action' => $action],
        );
    }

    public static function declarationMismatch(string $action, string $expected, string $given): self
    {
        return self::make(
            __('response-selection::selection.declaration_mismatch', [
                'action'   => $action,
                'expected' => $expected,
                'given'    => $given,
            ]),
            ['action' => $action, 'expected' => $expected, 'given' => $given],
        );
    }

    public static function resourceClassInvalid(string $resource): self
    {
        return self::make(
            __('response-selection::selection.resource_class_invalid', ['resource' => $resource]),
            ['resource' => $resource],
        );
    }

    public static function statusWithoutContent(mixed $status): self
    {
        return self::make(
            __('response-selection::selection.status_without_content'),
            ['status' => $status],
        );
    }

    public static function statusNotPossible(int $status): self
    {
        return self::make(
            __('response-selection::selection.status_not_possible', ['status' => $status]),
            ['status' => $status],
        );
    }

    public static function definitionRecursion(string $resource): self
    {
        return self::make(
            __('response-selection::selection.definition_recursion', ['resource' => $resource]),
            ['resource' => $resource],
        );
    }

    public static function selectionAlreadySet(string $resource): self
    {
        return self::make(
            __('response-selection::selection.selection_already_set', ['resource' => $resource]),
            ['resource' => $resource],
        );
    }

    public static function includeNotLoaded(string $resource, string $include): self
    {
        return self::make(
            __('response-selection::selection.include_not_loaded', [
                'resource' => $resource,
                'include'  => $include,
            ]),
            ['resource' => $resource, 'include' => $include],
        );
    }

    public static function renderedWithoutSelection(string $resource): self
    {
        return self::make(
            __('response-selection::selection.rendered_without_selection', ['resource' => $resource]),
            ['resource' => $resource],
        );
    }

    public static function fieldRendersRenderingUnit(string $resource, string $field, string $class): self
    {
        return self::make(
            __('response-selection::selection.field_renders_rendering_unit', [
                'resource' => $resource,
                'field'    => $field,
                'class'    => $class,
            ]),
            ['resource' => $resource, 'field' => $field, 'class' => $class],
        );
    }

    public static function childResourceUnresolvable(string $resource): self
    {
        return self::make(
            __('response-selection::selection.child_resource_unresolvable', ['resource' => $resource]),
            ['resource' => $resource],
        );
    }

    public static function resolverResourceNotListed(string $resolver, string $resource): self
    {
        return self::make(
            __('response-selection::selection.resolver_resource_not_listed', [
                'resolver' => $resolver,
                'resource' => $resource,
            ]),
            ['resolver' => $resolver, 'resource' => $resource],
        );
    }

    public static function definitionFrozen(): self
    {
        return self::make(__('response-selection::selection.definition_frozen'));
    }

    public static function memberDeclaredTwice(string $resource, string $name): self
    {
        return self::make(
            __('response-selection::selection.member_declared_twice', ['resource' => $resource, 'name' => $name]),
            ['resource' => $resource, 'name' => $name],
        );
    }

    public static function relationIncludedTwice(string $relation, string $first, string $second): self
    {
        return self::make(
            __('response-selection::selection.relation_included_twice', ['relation' => $relation, 'first' => $first, 'second' => $second]),
            ['relation' => $relation, 'first' => $first, 'second' => $second],
        );
    }

    public static function invalidName(string $name): self
    {
        return self::make(
            __('response-selection::selection.invalid_name', ['name' => $name]),
            ['name' => $name],
        );
    }

    public static function unknownFieldDependency(string $resource, string $member, string $field): self
    {
        return self::make(
            __('response-selection::selection.unknown_field_dependency', ['resource' => $resource, 'member' => $member, 'field' => $field]),
            ['resource' => $resource, 'member' => $member, 'field' => $field],
        );
    }

    public static function fieldDependsOnItself(string $resource, string $field): self
    {
        return self::make(
            __('response-selection::selection.field_depends_on_itself', ['resource' => $resource, 'field' => $field]),
            ['resource' => $resource, 'field' => $field],
        );
    }

    /** @param array<int, string> $cycle */
    public static function fieldDependencyCycle(string $resource, array $cycle): self
    {
        return self::make(
            __('response-selection::selection.field_dependency_cycle', ['resource' => $resource, 'cycle' => implode(' → ', $cycle)]),
            ['resource' => $resource, 'cycle' => $cycle],
        );
    }

    public static function builtFieldNotDeclared(string $resource, string $builder, string $field): self
    {
        return self::make(
            __('response-selection::selection.built_field_not_declared', ['resource' => $resource, 'builder' => $builder, 'field' => $field]),
            ['resource' => $resource, 'builder' => $builder, 'field' => $field],
        );
    }

    public static function builtFieldNotComputed(string $resource, string $builder, string $field): self
    {
        return self::make(
            __('response-selection::selection.built_field_not_computed', ['resource' => $resource, 'builder' => $builder, 'field' => $field]),
            ['resource' => $resource, 'builder' => $builder, 'field' => $field],
        );
    }

    public static function computedFieldNotComputed(string $resource, string $field): self
    {
        return self::make(
            __('response-selection::selection.computed_field_not_computed', ['resource' => $resource, 'field' => $field]),
            ['resource' => $resource, 'field' => $field],
        );
    }

    public static function builderModelWithoutKey(string $resource, string $builder, string $model): self
    {
        return self::make(
            __('response-selection::selection.builder_model_without_key', ['resource' => $resource, 'builder' => $builder, 'model' => $model]),
            ['resource' => $resource, 'builder' => $builder, 'model' => $model],
        );
    }

    public static function classMustImplement(string $class, string $contract): self
    {
        return self::make(
            __('response-selection::selection.class_must_implement', [
                'class'    => $class,
                'contract' => $contract,
            ]),
            ['class' => $class, 'contract' => $contract],
        );
    }

    public static function virtualIncludeNotModel(string $resource, string $include, string $type): self
    {
        return self::make(
            __('response-selection::selection.virtual_include_not_model', [
                'resource' => $resource,
                'include'  => $include,
                'type'     => $type,
            ]),
            ['resource' => $resource, 'include' => $include, 'type' => $type],
        );
    }

    public static function relationPathAfterMorphTo(string $resource, string $path, string $relation): self
    {
        return self::make(
            __('response-selection::selection.relation_path_after_morph_to', [
                'resource' => $resource,
                'path'     => $path,
                'relation' => $relation,
            ]),
            ['resource' => $resource, 'path' => $path, 'relation' => $relation],
        );
    }

    public static function relationMissing(string $resource, string $name, string $model): self
    {
        return self::make(
            __('response-selection::selection.relation_missing', [
                'resource' => $resource,
                'name'     => $name,
                'model'    => $model,
            ]),
            ['resource' => $resource, 'name' => $name, 'model' => $model],
        );
    }

    public static function notARelation(string $resource, string $name, string $model): self
    {
        return self::make(
            __('response-selection::selection.not_a_relation', [
                'resource' => $resource,
                'name'     => $name,
                'model'    => $model,
            ]),
            ['resource' => $resource, 'name' => $name, 'model' => $model],
        );
    }

    /** @param array<string, mixed> $context */
    private static function make(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    /** @return array<string, mixed> Machine-readable details, apart from the translated message. */
    public function context(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, private readonly array $context = [])
    {
        parent::__construct($message);
    }
}
