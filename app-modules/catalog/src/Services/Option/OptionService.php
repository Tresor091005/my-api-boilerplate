<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Option;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Shared\Contracts\Services\TransactionalService;

class OptionService implements TransactionalService
{
    /**
     * @param  Collection<int, array{name: string, value: string}>  $allOptionsData
     * @return Collection<string, OptionValue>
     */
    public function getOrCreate(Collection $allOptionsData): Collection
    {
        if ($allOptionsData->isEmpty()) {
            return collect();
        }

        $now = now();
        $organizationId = getPermissionsTeamId();

        $uniqueOptions = $allOptionsData->map(fn (array $item): array => [
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

        $options = Option::where('organization_id', $organizationId)
            ->whereIn('name', $uniqueOptions->pluck('name'))
            ->get()
            ->keyBy('name');

        $uniqueValues = $allOptionsData->map(function (array $item) use ($options, $now, $organizationId): ?array {
            $option = $options->get($item['name']);

            if (!$option) {
                return null;
            }

            return [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'option_id'       => $option->id,
                'value'           => $item['value'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        })->filter()->unique(fn ($item): string => "{$item['option_id']}-{$item['value']}");

        if ($uniqueValues->isNotEmpty()) {
            $valueBindings = [];
            $valuePlaceholders = $uniqueValues->map(function (array $valueData) use (&$valueBindings): string {
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
                 DO UPDATE SET updated_at = EXCLUDED.updated_at",
                $valueBindings
            );
        }

        return OptionValue::with('option')->where('organization_id', $organizationId)
            ->whereIn('option_id', $options->pluck('id'))
            ->get()
            ->keyBy(fn ($item): string => $item->option->name.'-'.$item->value);
    }
}
