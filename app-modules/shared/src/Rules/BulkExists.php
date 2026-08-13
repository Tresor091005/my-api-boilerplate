<?php

declare(strict_types=1);

namespace Lahatre\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validate every unique identifier in an array with one constrained query.
 *
 * The Rule accepts either a flat identifier list or a list of objects from
 * which one identifier key is extracted. It remains value-aware only; use
 * DataAwareRule or ValidatorAwareRule in a dedicated Rule when validation
 * genuinely needs sibling payload fields or precise multi-path failures.
 */
class BulkExists implements ValidationRule
{
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
        protected ?string $keyInArray = null,
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

        $rawIds = collect($value)
            ->map(fn ($item): mixed => $this->keyInArray ? ($item[$this->keyInArray] ?? null) : $item)
            ->filter()
            ->unique();

        if ($rawIds->isEmpty()) {
            return;
        }

        // Filter valid IDs based on type to prevent DB crashes (especially PostgreSQL UUIDs)
        $validIds = $rawIds->filter(fn ($id): bool => match ($this->type) {
            'uuid'  => is_string($id) && Str::isUuid($id),
            'int'   => is_numeric($id),
            default => is_string($id) || is_numeric($id),
        });

        if ($validIds->count() !== $rawIds->count()) {
            $fail(__('shared::validation.bulk_exists', ['attribute' => $attribute]));

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

        $existingCount = $query->count();

        if ($existingCount !== $validIds->count()) {
            $fail(__('shared::validation.bulk_exists', ['attribute' => $attribute]));
        }
    }
}
