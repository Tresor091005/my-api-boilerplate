<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

/** Business-rule violations for labelable models and label links. */
final class LabelException extends AssertionException
{
    /** @param array<int, string> $values */
    public static function notFound(string $group, array $values): self
    {
        return self::message(__('master::exceptions.label_not_found', ['group' => $group, 'values' => implode(', ', $values)]));
    }

    /** @param array<int, string> $values */
    public static function linkNotFound(string $group, array $values): self
    {
        return self::message(__('master::exceptions.label_link_not_found', ['group' => $group, 'values' => implode(', ', $values)]));
    }

    public static function modelMissingInteractsWithLabelsTrait(string $model): self
    {
        return self::message(__('master::exceptions.model_missing_interacts_with_labels_trait', ['model' => $model]));
    }

    public static function organizationResolutionFailed(): self
    {
        return self::message(__('master::exceptions.organization_resolution_failed'));
    }

    public static function organizationMismatch(): self
    {
        return self::message(__('master::exceptions.organization_mismatch'));
    }

    /** @param array<int, array{labelable_type: string, labelable_id: string}> $usages */
    public static function inUse(array $usages): self
    {
        return self::message(__('master::exceptions.label_in_use', ['count' => count($usages)]), ['usages' => $usages]);
    }

    /** @param array<int, string> $missingLabelIds @param array<int, string> $unexpectedLabelIds */
    public static function reorderMismatch(array $missingLabelIds, array $unexpectedLabelIds): self
    {
        return self::message(__('master::exceptions.label_reorder_mismatch'), [
            'missing_label_ids'    => $missingLabelIds,
            'unexpected_label_ids' => $unexpectedLabelIds,
        ]);
    }

    private static function message(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
