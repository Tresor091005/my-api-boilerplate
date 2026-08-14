<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Option;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;

class TransactionalOptionService
{
    /**
     * @param  Collection<int, array{name: string, value: string}>  $optionsData
     * @return Collection<string, OptionValue>
     */
    public function resolveOrCreateValues(Collection $optionsData): Collection
    {
        if ($optionsData->isEmpty()) {
            return collect();
        }

        $now = now();
        $organizationId = currentOrganizationId();

        $uniqueOptions = $optionsData->map(fn (array $item): array => [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            'name'            => $item['name'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->unique('name')->values();

        if ($uniqueOptions->isNotEmpty()) {
            $optionBindings = [];
            $optionPlaceholders = $uniqueOptions->map(function (array $optionData) use (&$optionBindings): string {
                $optionBindings[] = $optionData['id'];
                $optionBindings[] = $optionData['organization_id'];
                $optionBindings[] = $optionData['name'];
                $optionBindings[] = $optionData['created_at'];
                $optionBindings[] = $optionData['updated_at'];

                return '(?, ?, ?, ?, ?)';
            })->implode(', ');

            DB::statement(
                "INSERT INTO catalog_options (id, organization_id, name, created_at, updated_at)
                 VALUES {$optionPlaceholders}
                 ON CONFLICT (organization_id, name) WHERE deleted_at IS NULL
                 DO UPDATE SET updated_at = EXCLUDED.updated_at",
                $optionBindings
            );
        }

        $options = Option::query()
            ->where('organization_id', $organizationId)
            ->whereIn('name', $uniqueOptions->pluck('name'))
            ->get()
            ->keyBy('name');

        $uniqueValues = $optionsData->map(function (array $item) use ($options, $now, $organizationId): ?array {
            $option = $options->get($item['name']);

            if (!$option) {
                return null;
            }

            return [
                'id'              => (string) Str::uuid7(),
                'organization_id' => (string) $organizationId,
                'option_id'       => (string) $option->id,
                'value'           => $item['value'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        })->filter()->unique(fn (array $item): string => "{$item['option_id']}-{$item['value']}");

        $this->insertOptionValueRows($uniqueValues->values()->all());

        return OptionValue::query()
            ->with('option')
            ->where('organization_id', $organizationId)
            ->whereIn('option_id', $options->pluck('id'))
            ->get()
            ->keyBy(fn (OptionValue $optionValue): string => $optionValue->option->name.'-'.$optionValue->value);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $values
     * @return EloquentCollection<int, OptionValue>
     */
    public function createMissingValues(Option $option, Collection|array $values): EloquentCollection
    {
        $normalizedValues = collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values();

        if ($normalizedValues->isEmpty()) {
            return new EloquentCollection;
        }

        $existingValues = $option->values()
            ->whereIn('value', $normalizedValues)
            ->get()
            ->keyBy('value');

        $missingValues = $normalizedValues
            ->reject(fn (string $value): bool => $existingValues->has($value))
            ->values();

        if ($missingValues->isEmpty()) {
            /** @var array<int, OptionValue> $existingOptionValues */
            $existingOptionValues = $existingValues->values()->all();

            return new EloquentCollection($existingOptionValues);
        }

        $now = now();

        $this->insertOptionValueRows(
            $missingValues->map(fn (string $value): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => (string) $option->organization_id,
                'option_id'       => (string) $option->id,
                'value'           => $value,
                'created_at'      => $now,
                'updated_at'      => $now,
            ])->all()
        );

        /** @var EloquentCollection<int, OptionValue> $optionValues */
        $optionValues = $option->values()
            ->whereIn('value', $normalizedValues)
            ->get();

        return $optionValues;
    }

    /**
     * @param  array<int, array{id: string, organization_id: string, option_id: string, value: string, created_at: mixed, updated_at: mixed}>  $valueRows
     */
    protected function insertOptionValueRows(array $valueRows): void
    {
        if ($valueRows === []) {
            return;
        }

        $valueBindings = [];
        $valuePlaceholders = collect($valueRows)->map(function (array $valueData) use (&$valueBindings): string {
            $valueBindings[] = $valueData['id'];
            $valueBindings[] = $valueData['organization_id'];
            $valueBindings[] = $valueData['option_id'];
            $valueBindings[] = $valueData['value'];
            $valueBindings[] = $valueData['created_at'];
            $valueBindings[] = $valueData['updated_at'];

            return '(?, ?, ?, ?, ?, ?)';
        })->implode(', ');

        DB::statement(
            "INSERT INTO catalog_option_values (id, organization_id, option_id, value, created_at, updated_at)
             VALUES {$valuePlaceholders}
             ON CONFLICT (organization_id, option_id, value) WHERE deleted_at IS NULL
             DO NOTHING",
            $valueBindings
        );
    }
}
