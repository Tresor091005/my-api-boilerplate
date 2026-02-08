<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO;

use Lahatre\Shared\DTO\Traits\HasBeforeValidation;
use Lahatre\Shared\DTO\Traits\UpdatesFromModel;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Class LahatreDTO
 *
 * Base Data Transfer Object class for the application.
 * Extends the functionality of WendellAdriel\ValidatedDTO with:
 *
 * 1. **Model Context Awareness**: Can store the ID of the model being updated (`UpdatesFromModel`).
 *    This is crucial for validation rules like `Rule::unique(...)->ignore($this->modelId)`.
 *
 * 2. **Pre-Validation Hook**: Allows data manipulation before validation runs (`HasBeforeValidation`),
 *    similar to `prepareForValidation` in FormRequests.
 *
 * Usage Example:
 * ```php
 * class UserDTO extends LahatreDTO
 * {
 *     protected function rules(): array
 *     {
 *         return [
 *             'email' => [
 *                 'required',
 *                 'email',
 *                 Rule::unique('users', 'email')->ignore($this->modelId),
 *             ],
 *         ];
 *     }
 * }
 *
 * // For Creation:
 * $dto = UserDTO::fromRequest($request);
 *
 * // For Update:
 * $dto = UserDTO::forUpdate($request, $userModel);
 * ```
 */
abstract class LahatreDTO extends ValidatedDTO
{
    use HasBeforeValidation;
    use UpdatesFromModel;
}
