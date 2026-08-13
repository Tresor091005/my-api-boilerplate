<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantFilterData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Http\Resources\ProductVariantCollection;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Variant\TransactionalProductVariantService;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

class ProductVariantService
{
    public function __construct(
        protected ProductVariantAssertion $productVariantAssertion,
        protected InventoryInterface $inventoryService,
        protected TransactionalProductVariantService $transactionalProductVariantService,
    ) {}

    public function list(Product $product, ProductVariantFilterData $filters): ProductVariantCollection
    {
        $query = $product->variants()->where('organization_id', getPermissionsTeamId())->with($this->relations());

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        $variants = stableCursorPaginate($query, $filters);

        return ProductVariantCollection::make($variants);
    }

    public function retrieve(Product $product, ProductVariant $variant): ProductVariantResource
    {
        $this->productVariantAssertion->assertBelongsToProduct($product, $variant);

        return ProductVariantResource::make($variant->load($this->relations()));
    }

    public function create(Product $product, ProductVariantBatchData $data): AnonymousResourceCollection
    {
        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = DB::transaction(
            fn (): EloquentCollection => $this->transactionalProductVariantService->createMany($product, $data->variants)
        );

        $variants->load($this->relations());

        return ProductVariantResource::collection($variants);
    }

    public function update(Product $product, ProductVariant $variant, ProductVariantUpdateData $data): ProductVariantResource
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

            $this->inventoryService->updateItem($variant, ['sku' => $variant->sku]);
        });

        return ProductVariantResource::make($variant->load($this->relations()));
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
     * @return array<int|string, mixed>
     */
    protected function relations(): array
    {
        return [
            'product',
            'optionValues.option',
            'unitGroup',
            'inventoryItem.stockSummaries',
        ];
    }
}
