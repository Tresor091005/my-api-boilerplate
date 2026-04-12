<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Option;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Shared\Contracts\Services\TransactionalService;

class TransactionalOptionService implements TransactionalService
{
    /**
     * @param  array<int, string>|Collection<int, string>  $values
     * @return Collection<int, OptionValue>
     */
    public function createMissingValues(Option $option, Collection|array $values): Collection
    {
        $normalizedValues = collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();

        if ($normalizedValues->isEmpty()) {
            return collect();
        }

        $existingValues = $option->values()
            ->whereIn('value', $normalizedValues)
            ->get()
            ->keyBy('value');

        $missingValues = $normalizedValues
            ->reject(fn (string $value): bool => $existingValues->has($value))
            ->values();

        if ($missingValues->isEmpty()) {
            return $existingValues->values();
        }

        $now = now();

        OptionValue::insert(
            $missingValues->map(fn (string $value): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $option->organization_id,
                'option_id'       => $option->id,
                'value'           => $value,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->all()
        );

        return $option->values()
            ->whereIn('value', $normalizedValues)
            ->get();
    }
}
