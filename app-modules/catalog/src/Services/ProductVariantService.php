<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
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
    /** @var list<string> */
    private const DEFAULT_REQUIRED_RELATIONS = ['product', 'optionValues.option', 'unitGroup'];

    public function __construct(
        protected InventoryInterface $inventoryService,
        protected ProductVariantAssertion $productVariantAssertion,
        protected TransactionalProductVariantService $transactionalProductVariantService,
    ) {}

    public function paginate(Product $product, ProductVariantFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery(
                $this->variantsQuery($product, $filters),
                self::DEFAULT_REQUIRED_RELATIONS,
            ),
            $filters,
        );
    }

    public function retrieve(Product $product, ProductVariant $variant): ProductVariant
    {
        $this->productVariantAssertion->assertBelongsToProduct($product, $variant);

        return $variant->load(responseRelationsToLoad(self::DEFAULT_REQUIRED_RELATIONS));
    }

    public function create(Product $product, ProductVariantBatchData $data): EloquentCollection
    {
        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = DB::transaction(
            fn (): EloquentCollection => $this->transactionalProductVariantService->createMany($product, $data->variants)
        );

        return $variants->load(responseRelationsToLoad(self::DEFAULT_REQUIRED_RELATIONS));
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

            if (!$data->tags instanceof MissingValue) {
                foreach ($data->tags as $type => $tags) {
                    $variant->syncTagsForType($type, $tags);
                }
            }

            $this->inventoryService->updateItem($variant, ['sku' => $variant->sku]);
        });

        return $variant->load(responseRelationsToLoad(self::DEFAULT_REQUIRED_RELATIONS));
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
        $query = $product->variants()->getQuery()->where('organization_id', currentOrganizationId());

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        return $query;
    }
}
