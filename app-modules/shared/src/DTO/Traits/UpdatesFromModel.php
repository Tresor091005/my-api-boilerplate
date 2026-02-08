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
     * Initialize the DTO with an optional Model ID.
     * This overrides the default constructor to handle the model ID injection.
     *
     * @param  array  $data
     */
    public function __construct(?array $data = null, int|string|null $modelId = null)
    {
        $this->modelId = $modelId;
        parent::__construct($data);
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

        return new static(
            $mergedData,
            $model->getKey()
        );
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
