<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\OptionValueAssertion;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Http\Resources\OptionValueResource;
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

    public function list(Option $option, OptionValueFilterData $filters): AnonymousResourceCollection
    {
        $query = $option->values()->where('organization_id', getPermissionsTeamId());

        if ($filters->value) {
            $query->where('value', 'like', "$filters->value%");
        }

        $query->orderBy($filters->sortBy, $filters->sortOrder);

        return OptionValueResource::collection($query->get());
    }

    public function retrieve(Option $option, OptionValue $optionValue): OptionValueResource
    {
        return OptionValueResource::make($optionValue);
    }

    public function create(Option $option, OptionValueData $data): AnonymousResourceCollection
    {
        $optionValues = DB::transaction(
            fn (): Collection => $this->transactionalOptionService->createMissingValues(
                $option,
                required($data->values) ?? [],
            )
        );

        return OptionValueResource::collection($optionValues);
    }

    public function update(Option $option, OptionValue $optionValue, OptionValueData $data): OptionValueResource
    {
        $optionValue->fill(withoutMissing([
            'value' => $data->value,
        ]));

        DB::transaction(fn (): bool => $optionValue->save());

        return OptionValueResource::make($optionValue);
    }

    public function delete(Option $option, OptionValue $optionValue): void
    {
        $this->optionValueAssertion->assertCanDelete($optionValue);

        DB::transaction(fn (): ?bool => $optionValue->delete());
    }
}
