<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use JsonSerializable;
use Lahatre\Shared\DTO\Casts\ArrayCast;
use Lahatre\Shared\DTO\Casts\BooleanCast;
use Lahatre\Shared\DTO\Casts\CarbonImmutableCast;
use Lahatre\Shared\DTO\Casts\Castable;
use Lahatre\Shared\DTO\Casts\CollectionCast;
use Lahatre\Shared\DTO\Casts\DTOCast;
use Lahatre\Shared\DTO\Casts\EnumCast;
use Lahatre\Shared\DTO\Casts\FloatCast;
use Lahatre\Shared\DTO\Casts\IntegerCast;
use Lahatre\Shared\DTO\Casts\ObjectCast;
use Lahatre\Shared\DTO\Casts\StringCast;

/**
 * Class LahatreDTO
 *
 * Base Data Transfer Object class for the application.
 */
abstract class LahatreDTO implements Arrayable, JsonSerializable
{
    /**
     * The data that has been validated and casted.
     */
    protected array $validatedData = [];

    /**
     * The raw data prepared for validation.
     */
    protected array $dtoData = [];

    /**
     * The ID of the model being updated (if applicable).
     */
    protected int|string|null $modelId = null;

    /**
     * Create a new DTO instance.
     */
    public function __construct(array $data = [], int|string|null $modelId = null)
    {
        $this->modelId = $modelId;

        // 1. Merge Defaults
        $data = array_merge($this->defaults(), $data);

        // 2. Before Validation Hook (Transformation)
        $this->dtoData = $this->beforeValidation($data);

        // 3. Validation
        $validator = Validator::make($this->dtoData, $this->rules(), $this->messages(), $this->attributes());

        // 4. After Hook (Add logic/rules to validator)
        $this->after($validator);

        // Execute validation and get validated data
        $validated = $validator->validate();

        // 5. Cast & Hydrate
        $this->hydrate($validated);
    }

    /**
     * Create a DTO instance from a Request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static($request->all());
    }

    /**
     * Create a DTO instance for an update operation.
     */
    public static function forUpdate(Request $request, \Illuminate\Database\Eloquent\Model $model): static
    {
        $exclude = array_merge(
            $model->getHidden(),
            ['created_at', 'updated_at', 'deleted_at']
        );

        $modelData = collect($model->getAttributes())
            ->except($exclude)
            ->toArray();

        $mergedData = array_merge($modelData, $request->all());

        return new static($mergedData, $model->getKey());
    }

    /**
     * Define the validation rules.
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Define the default values.
     */
    protected function defaults(): array
    {
        return [];
    }

    /**
     * Define the casting rules.
     * Example: ['category' => CategoryDTO::class, 'tags' => [TagDTO::class], 'is_active' => 'boolean']
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Backward compatibility hook for older DTOs.
     */
    protected function beforeValidation(array $data): array
    {
        return $data;
    }

    /**
     * Hook after validator creation.
     */
    protected function after(LaravelValidator $validator): void
    {
        //
    }

    /**
     * Custom messages for validation.
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Custom attributes for validation.
     */
    protected function attributes(): array
    {
        return [];
    }

    /**
     * Hydrate the DTO properties with validated and casted data.
     */
    protected function hydrate(array $data): void
    {
        $casts = $this->casts();
        $this->validatedData = [];

        foreach ($data as $key => $value) {
            $cast = $casts[$key] ?? null;
            $castedValue = $this->castValue($key, $value, $cast);
            
            if (!property_exists($this, $key)) {
                throw new \LogicException("Unknown DTO property [$key]");
            }

            $this->{$key} = $castedValue;
            
            $this->validatedData[$key] = $castedValue;
        }
    }

    /**
     * Cast a value based on the defined rules.
     */
    protected function castValue(string $key, mixed $value, mixed $cast): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_null($cast)) {
            return $value;
        }

        // Handle Castable instance
        if ($cast instanceof Castable) {
            return $cast->cast($key, $value);
        }

        // Handle legacy/custom objects with cast method
        if (is_object($cast) && method_exists($cast, 'cast')) {
            return $cast->cast($key, $value);
        }

        // Handle Closure cast
        if (is_callable($cast)) {
            return $cast($value);
        }

        // Handle array of something (recursive list)
        if (is_array($cast)) {
            $target = $cast[0] ?? null;
            if ($target) {
                return (new ArrayCast($this->resolveCastable($target)))->cast($key, $value);
            }
            return (array) $value;
        }

        // Handle string types (bool, int, etc) or class names
        if (is_string($cast)) {
            return $this->resolveCastable($cast)->cast($key, $value);
        }

        return $value;
    }

    /**
     * Resolve a string or class name into a Castable instance.
     */
    protected function resolveCastable(string $cast): Castable
    {
        [$type, $param] = str_contains($cast, ':') ? explode(':', $cast, 2) : [$cast, null];

        return match ($type) {
            'bool', 'boolean' => new BooleanCast(),
            'int', 'integer' => new IntegerCast(),
            'float', 'double' => new FloatCast(),
            'string' => new StringCast(),
            'object' => new ObjectCast(),
            'carbonImmutable', 'immutable_datetime' => new CarbonImmutableCast(),
            'array' => new ArrayCast($param ? $this->resolveCastable($param) : null),
            'collection' => new CollectionCast($param ? $this->resolveCastable($param) : null),
            default => $this->resolveComplexCast($cast),
        };
    }

    /**
     * Resolve complex types like DTOs or Enums.
     */
    protected function resolveComplexCast(string $cast): Castable
    {
        if (is_subclass_of($cast, self::class)) {
            return new DTOCast($cast);
        }

        if (enum_exists($cast)) {
            return new EnumCast($cast);
        }

        // Fallback to a custom Castable class if it exists
        if (class_exists($cast) && is_subclass_of($cast, Castable::class)) {
            return new $cast();
        }

        return new StringCast();
    }

    /**
     * Convert the DTO to an array.
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->validatedData as $key => $value) {
            $result[$key] = $this->transformValueForArray($value);
        }
        return $result;
    }

    /**
     * Recursive transformation for toArray.
     */
    private function transformValueForArray(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn($item) => $this->transformValueForArray($item), $value);
        }

        return $value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
