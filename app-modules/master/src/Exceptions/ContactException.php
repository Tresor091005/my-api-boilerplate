<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class ContactException extends AssertionException
{
    public static function modelMissingInteractsWithContactsTrait(string $model): self
    {
        return new self(__('master::exceptions.model_missing_interacts_with_contacts_trait', ['model' => $model]));
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }

    public static function multiplePrimary(): self
    {
        return new self(__('master::exceptions.multiple_primary_contacts'));
    }

    /** @param array<int, string> $ids */
    public static function invalidIds(array $ids): self
    {
        return new self(__('master::exceptions.invalid_contact_ids'), ['invalid_ids' => $ids]);
    }
}
