<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Option;

use Illuminate\Support\Collection;
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

        $uniqueOptions = $allOptionsData->map(fn (array $item): array => [
            'id'         => (string) Str::uuid7(),
            'name'       => $item['name'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->unique('name')->values();

        Option::upsert(
            $uniqueOptions->toArray(),
            ['name'],
            ['updated_at']
        );

        $options = Option::whereIn('name', $uniqueOptions->pluck('name'))
            ->get()
            ->keyBy('name');

        $uniqueValues = $allOptionsData->map(function (array $item) use ($options, $now): ?array {
            $option = $options->get($item['name']);

            if (!$option) {
                return null;
            }

            return [
                'id'         => (string) Str::uuid7(),
                'option_id'  => $option->id,
                'value'      => $item['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->filter()->unique(fn ($item): string => "{$item['option_id']}-{$item['value']}");

        OptionValue::upsert(
            $uniqueValues->toArray(),
            ['option_id', 'value'],
            ['updated_at']
        );

        return OptionValue::with('option')->whereIn('option_id', $options->pluck('id'))
            ->get()
            ->keyBy(fn ($item): string => $item->option->name.'-'.$item->value);
    }
}
