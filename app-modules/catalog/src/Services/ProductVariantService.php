<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Variant\TransactionalProductVariantService;
use Lahatre\Shared\Data\MissingValue;

class ProductVariantService
{
    public function __construct(
        protected ProductVariantAssertion $productVariantAssertion,
        protected TransactionalCatalogItemService $transactionalCatalogItemService,
        protected TransactionalProductVariantService $transactionalProductVariantService,
    ) {}

    public function paginate(Product $product, ProductVariantFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->variantsQuery($product, $filters)),
            $filters,
            tieBreakerColumn: 'catalog_product_variants.id',
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
            /** @var CatalogItem $catalogItem */
            $catalogItem = $variant->catalogItem()->firstOrFail();

            try {
                $this->transactionalCatalogItemService->update(
                    $catalogItem,
                    $data->catalogItem(),
                );
            } catch (ValidationException $exception) {
                $errors = collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["inventory.{$field}" => $messages])
                    ->all();

                throw ValidationException::withMessages($errors);
            }

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
        $organizationId = currentOrganizationId();

        /** @var Builder<ProductVariant> $query */
        $query = $product->variants()
            ->getQuery()
            ->join('catalog_items', function (JoinClause $join): void {
                $join->on('catalog_items.id', '=', 'catalog_product_variants.id')
                    ->on('catalog_items.organization_id', '=', 'catalog_product_variants.organization_id');
            })
            ->where('catalog_product_variants.organization_id', $organizationId)
            ->where('catalog_items.organization_id', $organizationId)
            ->whereNull('catalog_items.deleted_at')
            ->select([
                'catalog_product_variants.*',
                'catalog_items.sku as catalog_item_sku',
                'catalog_items.is_active as catalog_item_is_active',
            ]);

        if ($filters->isActive !== null) {
            $query->where('catalog_items.is_active', $filters->isActive);
        }

        return $query;
    }
}
