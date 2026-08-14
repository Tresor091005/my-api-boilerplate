<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;

/**
 * Validate unique values in an array with one constrained query.
 */
final class BulkUnique implements ValidationRule, ValidatorAwareRule
{
    protected Validator $validator;

    /**
     * @param  array<string, mixed>|array<int, Closure(Builder): void>  $extraConditions
     */
    public function __construct(
        protected string $table,
        protected string $column,
        protected string $keyInArray = 'id',
        protected bool $handleSoftDelete = false,
        protected array $extraConditions = []
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $items = collect($value)
            ->map(fn (mixed $item, int|string $index): array => [
                'index'  => $index,
                'nested' => is_array($item),
                'value'  => is_array($item)
                    ? ($item[$this->keyInArray] ?? null)
                    : $item,
            ])
            ->filter(fn (array $item): bool => is_string($item['value']) && $item['value'] !== '');

        $values = $items->pluck('value')->values();

        if ($values->isEmpty()) {
            return;
        }

        $invalidIndexes = [];
        foreach ($items->groupBy('value') as $group) {
            if ($group->count() > 1) {
                foreach ($group as $item) {
                    $invalidIndexes[(string) $item['index']] = true;
                }
            }
        }

        $query = DB::table($this->table)->whereIn($this->column, $values->unique()->all());

        foreach ($this->extraConditions as $key => $condition) {
            if (is_callable($condition) && is_numeric($key)) {
                $query->where($condition);
            } else {
                $query->where($key, $condition);
            }
        }

        if ($this->handleSoftDelete) {
            $query->whereNull('deleted_at');
        }

        $existingValues = $query->pluck($this->column)->all();
        foreach ($items as $item) {
            if (in_array($item['value'], $existingValues, true)) {
                $invalidIndexes[(string) $item['index']] = true;
            }
        }

        foreach ($items as $item) {
            if (isset($invalidIndexes[(string) $item['index']])) {
                $this->addError($attribute, $item['index'], $item['nested'], __('shared::validation.bulk_unique', ['attribute' => $attribute]));
            }
        }
    }

    /**
     * Receive the Laravel validator before validation starts.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    protected function addError(string $attribute, int|string $index, bool $nested, string $message): void
    {
        $errorAttribute = $nested
            ? "{$attribute}.{$index}.{$this->keyInArray}"
            : "{$attribute}.{$index}";

        $this->validator->errors()->add($errorAttribute, $message);
    }
}
