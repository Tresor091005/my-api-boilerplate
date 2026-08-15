<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionAssertion;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionFilterData;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

class OptionService
{
    public function __construct(
        protected OptionAssertion $optionAssertion,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    public function paginate(OptionFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->optionsQuery($filters)),
            $filters,
        );
    }

    /** @return Builder<Option> */
    private function optionsQuery(OptionFilterData $filters): Builder
    {
        $query = Option::query()->where('organization_id', currentOrganizationId());

        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }

        return $query;
    }

    public function retrieve(Option $option): Option
    {
        return $option->load(responseRelationsToLoad());
    }

    public function create(OptionData $data): Option
    {
        $option = new Option;

        $option->fill([
            'organization_id' => currentOrganizationId(),
            'name'            => required($data->name),
        ]);

        DB::transaction(function () use ($option, $data): void {
            $option->save();

            $this->transactionalOptionService->createMissingValues(
                $option,
                required($data->values) ?? [],
            );
        });

        return $option->load(responseRelationsToLoad());
    }

    public function update(Option $option, OptionData $data): Option
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

        return $option->load(responseRelationsToLoad());
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
