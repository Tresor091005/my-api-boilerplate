<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

/**
 * Business-rule violations for units and their unit groups.
 */
final class UnitException extends AssertionException
{
    public static function baseDeactivation(): self
    {
        return self::message(__('master::exceptions.unit_base_deactivation'));
    }

    public static function ratioConflict(float|int $ratio, string $group): self
    {
        return self::message(__('master::exceptions.unit_ratio_exists_in_group', ['ratio' => $ratio, 'group' => $group]));
    }

    public static function duplicateRatio(): self
    {
        return self::message(__('master::exceptions.unit_duplicate_ratio'));
    }

    public static function activeLimit(int $limit): self
    {
        return self::message(__('master::exceptions.unit_active_limit', ['limit' => $limit]));
    }

    public static function baseRequired(): self
    {
        return self::message(__('master::exceptions.unit_base_required'));
    }

    public static function ratioRequired(): self
    {
        return self::message(__('master::exceptions.unit_ratio_required'));
    }

    public static function ratioImmutable(): self
    {
        return self::message(__('master::exceptions.unit_ratio_immutable'));
    }

    public static function builtInUpdate(): self
    {
        return self::message(__('master::exceptions.unit_builtin_update'));
    }

    public static function groupMismatch(string $unitId, string $group): self
    {
        return self::message(__('master::exceptions.unit_group_mismatch', ['unit_id' => $unitId, 'group' => $group]));
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
