<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionAssertion;
use Lahatre\Catalog\DTO\OptionDTO;
use Lahatre\Catalog\DTO\OptionFilterDTO;
use Lahatre\Catalog\Http\Resources\OptionCollection;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class OptionService implements StandaloneService
{
    public function __construct(
        protected OptionAssertion $optionAssertion,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    public function list(OptionFilterDTO $filters): OptionCollection
    {
        $query = Option::query()->where('organization_id', getPermissionsTeamId());

        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $options = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return OptionCollection::make($options);
    }

    public function retrieve(Option $option): OptionResource
    {
        return OptionResource::make($option->load(['values']));
    }

    public function create(OptionDTO $dto): OptionResource
    {
        $option = new Option();

        $option->fill([
            'organization_id' => getPermissionsTeamId(),
            'name'            => $dto->name,
        ]);

        DB::transaction(function () use ($option, $dto): void {
            $option->save();

            $this->transactionalOptionService->createMissingValues($option, $dto->values ?? []);
        });

        return OptionResource::make($option->load(['values']));
    }

    public function update(Option $option, OptionDTO $dto): OptionResource
    {
        $option->fill([
            'name' => $dto->name,
        ]);

        DB::transaction(function () use ($option, $dto): void {
            $option->save();

            $this->transactionalOptionService->createMissingValues($option, $dto->values ?? []);
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
