<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

/** Business-rule violations for taggable models and tag links. */
final class TagException extends AssertionException
{
    /** @param array<int, string> $names */
    public static function notFound(string $type, array $names): self
    {
        return self::message(__('master::exceptions.tag_not_found', ['type' => $type, 'names' => implode(', ', $names)]));
    }

    /** @param array<int, string> $names */
    public static function linkNotFound(string $type, array $names): self
    {
        return self::message(__('master::exceptions.tag_link_not_found', ['type' => $type, 'names' => implode(', ', $names)]));
    }

    public static function modelMissingInteractsWithTagsTrait(string $model): self
    {
        return self::message(__('master::exceptions.model_missing_interacts_with_tags_trait', ['model' => $model]));
    }

    public static function organizationResolutionFailed(): self
    {
        return self::message(__('master::exceptions.organization_resolution_failed'));
    }

    public static function organizationMismatch(string $expectedOrganizationId, string $actualOrganizationId): self
    {
        return self::message(
            __('master::exceptions.organization_mismatch'),
            [
                'expected_organization_id' => $expectedOrganizationId,
                'actual_organization_id'   => $actualOrganizationId,
            ]
        );
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
