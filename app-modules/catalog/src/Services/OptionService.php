<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionAssertion;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionFilterData;
use Lahatre\Catalog\Http\Resources\OptionCollection;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

class OptionService implements StandaloneService
{
    public function __construct(
        protected OptionAssertion $optionAssertion,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    public function list(OptionFilterData $filters): OptionCollection
    {
        $query = Option::query()->where('organization_id', getPermissionsTeamId());

        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }

        $options = stableCursorPaginate($query, $filters);

        return OptionCollection::make($options);
    }

    public function retrieve(Option $option): OptionResource
    {
        return OptionResource::make($option->load(['values']));
    }

    public function create(OptionData $data): OptionResource
    {
        $option = new Option();

        $option->fill([
            'organization_id' => getPermissionsTeamId(),
            'name'            => required($data->name),
        ]);

        DB::transaction(function () use ($option, $data): void {
            $option->save();

            $this->transactionalOptionService->createMissingValues(
                $option,
                required($data->values) ?? [],
            );
        });

        return OptionResource::make($option->load(['values']));
    }

    public function update(Option $option, OptionData $data): OptionResource
    {
        $option->fill(withoutMissing([
            'name' => $data->name,
        ]));

        DB::transaction(function () use ($option, $data): void {
            $option->save();

            if (!$data->values instanceof MissingValue) {
                $this->transactionalOptionService->createMissingValues($option, $data->values ?? []);
            }
        });

        return OptionResource::make($option->load(['values']));
    }

    public function delete(Option $option): void
    {
        $this->optionAssertion->assertCanDelete($option);

        DB::transaction(function () use ($option): void {
            $option->values()->delete();
            $option->delete();
        });
    }
}
