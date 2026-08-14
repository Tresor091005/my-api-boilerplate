<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;

/**
 * Validate every identifier in an array with one constrained query and
 * preserve precise errors for invalid nested elements.
 *
 * The Rule accepts either a flat identifier list or a list of objects from
 * which one identifier key is extracted.
 */
class BulkExists implements ValidationRule, ValidatorAwareRule
{
    protected Validator $validator;

    /**
     * Create a new rule instance.
     *
     * @param  string  $table  The table to check.
     * @param  string  $column  The column to check (defaults to id).
     * @param  string  $keyInArray  The key to extract from the array of objects (if applicable).
     * @param  string  $type  The expected data type: 'uuid', 'string', 'int'.
     * @param  bool  $handleSoftDelete  Whether to filter out soft deleted records.
     * @param  array<string, mixed>|array<int, Closure(Builder): void>  $extraConditions  Additional tenant, ownership, or authenticity constraints.
     */
    public function __construct(
        protected string $table,
        protected string $column = 'id',
        protected string $keyInArray = 'id',
        protected string $type = 'uuid',
        protected bool $handleSoftDelete = false,
        protected array $extraConditions = []
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
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
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['value'] !== '');

        $rawIds = $items->pluck('value')->unique();

        if ($rawIds->isEmpty()) {
            return;
        }

        $validIds = $rawIds->filter(fn (mixed $id): bool => $this->isValidType($id));

        foreach ($items as $item) {
            if (!$this->isValidType($item['value'])) {
                $this->addError($attribute, $item['index'], $item['nested'], __('shared::validation.bulk_exists', ['attribute' => $attribute]));
            }
        }

        if ($validIds->isEmpty()) {
            return;
        }

        $query = DB::table($this->table)
            ->whereIn($this->column, $validIds->toArray());

        foreach ($this->extraConditions as $col => $val) {
            if (is_callable($val) && is_numeric($col)) {
                $query->where($val);
            } else {
                $query->where($col, $val);
            }
        }

        if ($this->handleSoftDelete) {
            $query->whereNull('deleted_at');
        }

        $existingIds = $query->pluck($this->column)->all();

        foreach ($items as $item) {
            if ($this->isValidType($item['value']) && !in_array($item['value'], $existingIds, true)) {
                $this->addError($attribute, $item['index'], $item['nested'], __('shared::validation.bulk_exists', ['attribute' => $attribute]));
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

    protected function isValidType(mixed $value): bool
    {
        return match ($this->type) {
            'uuid'  => is_string($value) && Str::isUuid($value),
            'int'   => is_numeric($value),
            default => is_string($value) || is_numeric($value),
        };
    }

    protected function addError(string $attribute, int|string $index, bool $nested, string $message): void
    {
        $errorAttribute = $nested
            ? "{$attribute}.{$index}.{$this->keyInArray}"
            : "{$attribute}.{$index}";

        $this->validator->errors()->add($errorAttribute, $message);
    }
}
