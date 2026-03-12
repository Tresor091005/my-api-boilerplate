<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use JsonSerializable;
use Lahatre\Shared\DTO\Concerns\HasCasting;
use Lahatre\Shared\DTO\Concerns\HasDataResolver;
use Lahatre\Shared\DTO\Concerns\HasDataTransformer;

/**
 * Class LahatreDTO
 *
 * Base Data Transfer Object class for the application.
 */
abstract class LahatreDTO implements Arrayable, JsonSerializable
{
    use HasCasting;
    use HasDataResolver;
    use HasDataTransformer;

    /**
     * The data that has been validated and casted.
     */
    protected array $validatedData = [];

    /**
     * The raw data prepared for validation.
     */
    protected array $dtoData = [];

    /**
     * Create a new DTO instance.
     */
    public function __construct(array $data = [], protected int|string|null $modelId = null)
    {
        // 1. Merge Defaults
        $data = array_merge($this->defaults(), $data);

        // 2. Global Sanitization (Trim and Squash spaces)
        $data = $this->sanitize($data);

        // 3. Before Validation Hook (Transformation)
        $this->dtoData = $this->beforeValidation($data);

        // 4. Validation
        $validator = Validator::make($this->dtoData, $this->rules(), $this->messages(), $this->attributes());

        // 5. Hook to configure the validator (add custom rules, etc.)
        $this->withValidator($validator);

        // 6. Execute validation and get validated data
        $validated = $validator->validate();

        // 7. Cast & Hydrate
        $this->hydrate($validated);
    }

    /**
     * Sanitize incoming data (trim and squash strings recursively).
     */
    protected function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim((string) preg_replace('/\s+/', ' ', $value));
                $data[$key] = $value === '' ? null : $value;
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }

    /**
     * Define the validation rules.
     */
    abstract protected function rules(): array;

    /**
     * Define the default values.
     */
    abstract protected function defaults(): array;

    /**
     * Define the casting rules.
     */
    abstract protected function casts(): array;

    /**
     * Transformation hook before validation.
     */
    protected function beforeValidation(array $data): array
    {
        return $data;
    }

    /**
     * Hook to configure the validator after its creation.
     */
    protected function withValidator(LaravelValidator $validator): void
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
            return array_map($this->transformValueForArray(...), $value);
        }

        return $value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
