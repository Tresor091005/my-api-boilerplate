<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionValueAssertion;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

class OptionValueService
{
    public function __construct(
        protected OptionValueAssertion $optionValueAssertion,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    public function list(Option $option, OptionValueFilterData $filters): EloquentCollection
    {
        $query = $option->values()->where('organization_id', currentOrganizationId());

        if ($filters->value) {
            $query->where('value', 'like', "$filters->value%");
        }

        $query->orderBy($filters->sortBy, $filters->sortOrder);

        $query->with(responseRelationsToLoad());

        return $query->get();
    }

    public function retrieve(Option $option, OptionValue $optionValue): OptionValue
    {
        $this->optionValueAssertion->assertBelongsToOption($option, $optionValue);

        return $optionValue->load(responseRelationsToLoad());
    }

    public function create(Option $option, OptionValueData $data): EloquentCollection
    {
        $optionValues = DB::transaction(
            fn (): EloquentCollection => $this->transactionalOptionService->createMissingValues(
                $option,
                required($data->values) ?? [],
            )
        );

        $optionValues->load(responseRelationsToLoad());

        return $optionValues;
    }

    public function update(Option $option, OptionValue $optionValue, OptionValueData $data): OptionValue
    {
        $this->optionValueAssertion->assertBelongsToOption($option, $optionValue);

        $optionValue->fill(withoutMissing([
            'value' => $data->value,
        ]));

        DB::transaction(fn (): bool => $optionValue->save());

        return $optionValue->load(responseRelationsToLoad());
    }

    public function delete(Option $option, OptionValue $optionValue): void
    {
        $this->optionValueAssertion->assertBelongsToOption($option, $optionValue);

        $this->optionValueAssertion->assertCanDelete($optionValue);

        DB::transaction(fn (): ?bool => $optionValue->delete());
    }
}
