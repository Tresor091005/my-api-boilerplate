<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionValueAssertion;
use Lahatre\Catalog\DTO\OptionValueDTO;
use Lahatre\Catalog\DTO\OptionValueFilterDTO;
use Lahatre\Catalog\Http\Resources\OptionValueResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class OptionValueService implements StandaloneService
{
    public function __construct(
        protected OptionValueAssertion $optionValueAssertion,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    public function list(Option $option, OptionValueFilterDTO $filters): AnonymousResourceCollection
    {
        if ($option->organization_id !== getPermissionsTeamId()) {
            throw (new ModelNotFoundException())->setModel(Option::class, [$option->id]);
        }

        $query = $option->values()->where('organization_id', getPermissionsTeamId());

        if ($filters->value) {
            $query->where('value', 'like', "$filters->value%");
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        return OptionValueResource::collection($query->get());
    }

    public function retrieve(Option $option, OptionValue $optionValue): OptionValueResource
    {
        if ($option->organization_id !== getPermissionsTeamId() || $optionValue->option_id !== $option->id) {
            throw (new ModelNotFoundException())->setModel(OptionValue::class, [$optionValue->id]);
        }

        return OptionValueResource::make($optionValue);
    }

    public function create(Option $option, OptionValueDTO $dto): AnonymousResourceCollection
    {
        if ($option->organization_id !== getPermissionsTeamId()) {
            throw (new ModelNotFoundException())->setModel(Option::class, [$option->id]);
        }

        $optionValues = DB::transaction(
            fn (): Collection => $this->transactionalOptionService->createMissingValues($option, $dto->values ?? [])
        );

        return OptionValueResource::collection($optionValues);
    }

    public function update(Option $option, OptionValue $optionValue, OptionValueDTO $dto): OptionValueResource
    {
        if ($option->organization_id !== getPermissionsTeamId() || $optionValue->option_id !== $option->id) {
            throw (new ModelNotFoundException())->setModel(OptionValue::class, [$optionValue->id]);
        }

        $optionValue->fill([
            'value' => $dto->value,
        ]);

        DB::transaction(fn (): bool => $optionValue->save());

        return OptionValueResource::make($optionValue);
    }

    public function delete(Option $option, OptionValue $optionValue): void
    {
        if ($option->organization_id !== getPermissionsTeamId() || $optionValue->option_id !== $option->id) {
            throw (new ModelNotFoundException())->setModel(OptionValue::class, [$optionValue->id]);
        }

        $this->optionValueAssertion->assertCanDelete($optionValue);

        DB::transaction(fn (): ?bool => $optionValue->delete());
    }
}
