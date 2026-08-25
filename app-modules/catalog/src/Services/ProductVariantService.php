<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Variant\TransactionalProductVariantService;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

class ProductVariantService
{
    public function __construct(
        protected InventoryInterface $inventoryService,
        protected ProductVariantAssertion $productVariantAssertion,
        protected TransactionalProductVariantService $transactionalProductVariantService,
    ) {}

    public function paginate(Product $product, ProductVariantFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->variantsQuery($product, $filters)),
            $filters,
        );
    }

    public function retrieve(Product $product, ProductVariant $variant): ProductVariant
    {
        $this->productVariantAssertion->assertBelongsToProduct($product, $variant);

        return $variant->load(responseRelationsToLoad());
    }

    public function create(Product $product, ProductVariantBatchData $data): EloquentCollection
    {
        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = DB::transaction(
            fn (): EloquentCollection => $this->transactionalProductVariantService->createMany($product, $data->variants)
        );

        return $variants->load(responseRelationsToLoad());
    }

    public function update(Product $product, ProductVariant $variant, ProductVariantUpdateData $data): ProductVariant
    {
        $this->productVariantAssertion->assertBelongsToProduct($product, $variant);

        DB::transaction(function () use ($product, $variant, $data): void {
            $variant->fill(withoutMissing([
                'sku'       => $data->sku,
                'is_active' => $data->isActive,
            ]));

            $variant->save();

            if (!$data->options instanceof MissingValue) {
                $this->transactionalProductVariantService->replaceOptions(
                    $product,
                    $variant,
                    $data->options,
                );
            }

            if (!$data->labels instanceof MissingValue) {
                foreach ($data->labels as $group => $labels) {
                    $variant->syncLabelsForGroup($group, $labels);
                }
            }

            $inventoryData = ['sku' => $variant->sku];

            if (!$data->inventory instanceof MissingValue) {
                $inventoryData = [
                    ...$inventoryData,
                    ...$data->inventory->toArray(),
                ];
            }

            $this->syncInventoryItem($variant, $inventoryData);
        });

        return $variant->load(responseRelationsToLoad());
    }

    public function delete(Product $product, ProductVariant $variant): void
    {
        $this->productVariantAssertion->assertBelongsToProduct($product, $variant);

        $this->productVariantAssertion->assertCanDelete($variant);

        DB::transaction(function () use ($variant): void {
            $this->transactionalProductVariantService->delete($variant);
        });
    }

    /**
     * @return Builder<ProductVariant>
     */
    private function variantsQuery(Product $product, ProductVariantFilterData $filters): Builder
    {
        /** @var Builder<ProductVariant> $query */
        $query = $product->variants()->getQuery()->where('organization_id', currentOrganizationId());

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        return $query;
    }

    /**
     * Keep Inventory validation errors attached to the nested Variant payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncInventoryItem(ProductVariant $variant, array $data): void
    {
        try {
            $this->inventoryService->updateItem($variant, $data);
        } catch (ValidationException $exception) {
            $errors = collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ["inventory.{$field}" => $messages])
                ->all();

            throw ValidationException::withMessages($errors);
        }
    }
}
