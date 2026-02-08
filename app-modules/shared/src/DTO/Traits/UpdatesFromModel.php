<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Trait UpdatesFromModel
 *
 * Provides functionality to initialize a DTO from a Model update context.
 * Useful for scenarios where validation rules depend on the model's ID (e.g., unique checks)
 * or when merging existing model data with request input.
 */
trait UpdatesFromModel
{
    /**
     * ID of the model being updated.
     * Can be integer, string (UUID/ULID), or null (for creation).
     */
    protected int|string|null $modelId = null;

    /**
     * Set the Model ID associated with this DTO.
     * This method should be called by the DTO's constructor when using this trait for update scenarios.
     */
    protected function setModelId(int|string|null $modelId): void
    {
        $this->modelId = $modelId;
    }

    /**
     * Factory method to create a DTO instance for an update operation.
     *
     * It merges the Model's current attributes with the Request data, ensuring
     * that the DTO represents the "final state" of the object after the update.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Model  $model  The model instance being updated.
     */
    public static function forUpdate(Request $request, Model $model): static
    {
        $exclude = array_merge(
            $model->getHidden(),
            ['created_at', 'updated_at', 'deleted_at']
        );

        $modelData = collect($model->getAttributes())
            ->except($exclude)
            ->toArray();

        $requestData = $request->all();

        $mergedData = array_merge($modelData, $requestData);

        $dto = new static($mergedData); // @phpstan-ignore-line PHPStan struggles with implicit constructor hydration from ValidatedDTO.
        $dto->setModelId($model->getKey());

        return $dto;
    }

    /**
     * Determine if this DTO instance represents an update operation.
     */
    public function isUpdate(): bool
    {
        return $this->modelId !== null;
    }

    /**
     * Determine if this DTO instance represents a create operation.
     */
    public function isCreate(): bool
    {
        return $this->modelId === null;
    }

    /**
     * Retrieve the Model ID associated with this DTO.
     */
    public function getModelId(): int|string|null
    {
        return $this->modelId;
    }
}
