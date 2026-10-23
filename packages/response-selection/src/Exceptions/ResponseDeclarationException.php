<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Exceptions;

use LogicException;
use Throwable;

/**
 * Response declaration or Resource definition rejected by the static ResponseDeclarationAnalyzer, or
 * while a definition freezes. A programming error, not a business failure.
 */
final class ResponseDeclarationException extends LogicException
{
    public static function dynamicFactoryCall(string $action): self
    {
        return self::make(
            __('response-selection::declaration.dynamic_factory_call', ['action' => $action]),
            ['action' => $action],
        );
    }

    public static function actionNotFound(string $action, string $file): self
    {
        return self::make(
            __('response-selection::declaration.action_not_found', ['action' => $action, 'file' => $file]),
            ['action' => $action, 'file' => $file],
        );
    }

    public static function cacheInvalid(string $path): self
    {
        return self::make(
            __('response-selection::declaration.cache_invalid', ['path' => $path]),
            ['path' => $path],
        );
    }

    public static function singleFactoryCallExpected(string $action): self
    {
        return self::make(
            __('response-selection::declaration.single_factory_call_expected', ['action' => $action]),
            ['action' => $action],
        );
    }

    public static function resourceClassNotLiteral(string $action, string $factory): self
    {
        return self::make(
            __('response-selection::declaration.resource_class_not_literal', [
                'action'  => $action,
                'factory' => $factory,
            ]),
            ['action' => $action, 'factory' => $factory],
        );
    }

    public static function statusNotLiteral(string $action, string $factory): self
    {
        return self::make(
            __('response-selection::declaration.status_not_literal', [
                'action'  => $action,
                'factory' => $factory,
            ]),
            ['action' => $action, 'factory' => $factory],
        );
    }

    public static function statusNotPossible(string $action, int $status): self
    {
        return self::make(
            __('response-selection::declaration.status_not_possible', [
                'action' => $action,
                'status' => $status,
            ]),
            ['action' => $action, 'status' => $status],
        );
    }

    public static function notAResponseFactory(string $method): self
    {
        return self::make(
            __('response-selection::declaration.not_a_response_factory', ['method' => $method]),
            ['method' => $method],
        );
    }

    public static function possibleStatusesNotLiteral(string $action): self
    {
        return self::make(
            __('response-selection::declaration.possible_statuses_not_literal', ['action' => $action]),
            ['action' => $action],
        );
    }

    public static function statusWithoutContent(string $action, int $status): self
    {
        return self::make(
            __('response-selection::declaration.status_without_content', [
                'action' => $action,
                'status' => $status,
            ]),
            ['action' => $action, 'status' => $status],
        );
    }

    public static function definitionNotBuilt(string $path, Throwable $previous): self
    {
        return self::make(
            __('response-selection::declaration.definition_not_built', [
                'path'   => $path,
                'reason' => $previous->getMessage(),
            ]),
            ['path' => $path, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function childResourcesNotResolved(string $path, Throwable $previous): self
    {
        return self::make(
            __('response-selection::declaration.child_resources_not_resolved', [
                'path'   => $path,
                'reason' => $previous->getMessage(),
            ]),
            ['path' => $path, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function includeWithoutChildResource(string $path): self
    {
        return self::make(
            __('response-selection::declaration.include_without_child_resource', ['path' => $path]),
            ['path' => $path],
        );
    }

    public static function invalidCondition(string $resource, string $member): self
    {
        return self::make(
            __('response-selection::declaration.invalid_condition', ['resource' => $resource, 'member' => $member]),
            ['resource' => $resource, 'member' => $member],
        );
    }

    public static function classNotConcrete(string $path, string $class, string $contract): self
    {
        return self::make(
            __('response-selection::declaration.class_not_concrete', [
                'path'     => $path,
                'class'    => $class,
                'contract' => $contract,
            ]),
            ['path' => $path, 'class' => $class, 'contract' => $contract],
        );
    }

    /** @param array<string, mixed> $context */
    private static function make(string $message, array $context = [], ?Throwable $previous = null): self
    {
        return new self($message, $context, $previous);
    }

    /** @return array<string, mixed> Stable machine-readable details, kept out of the translated message. */
    public function context(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, private readonly array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
