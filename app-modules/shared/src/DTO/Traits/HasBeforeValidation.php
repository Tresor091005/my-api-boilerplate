<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Traits;

/**
 * Trait HasBeforeValidation
 *
 * Allows DTOs to intercept and modify data before it is sent to the Laravel Validator.
 * This acts similarly to the `prepareForValidation` method in Laravel Form Requests.
 */
trait HasBeforeValidation
{
    /**
     * Internal method overriding the ValidatedDTO data build process.
     * It injects the `beforeValidation` hook.
     */
    protected function buildDataForValidation(array $data): array
    {
        $data = parent::buildDataForValidation($data);

        return $this->beforeValidation($data);
    }

    /**
     * User-defined hook to sanitize or modify data before validation.
     * Override this method in your concrete DTO classes.
     *
     * Example:
     * protected function beforeValidation(array $data): array
     * {
     *     if (isset($data['slug'])) {
     *         $data['slug'] = Str::slug($data['slug']);
     *     }
     *     return $data;
     * }
     *
     * @param  array  $data  The raw data prepared for validation.
     * @return array The modified data to be validated.
     */
    protected function beforeValidation(array $data): array
    {
        return $data;
    }
}
