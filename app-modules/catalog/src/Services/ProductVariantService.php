<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\DTO\ProductVariantDTO;
use Lahatre\Catalog\DTO\ProductVariantFilterDTO;
use Lahatre\Catalog\DTO\ProductVariantUpdateDTO;
use Lahatre\Catalog\Http\Resources\ProductVariantCollection;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Variant\ProductVariantService as TransactionalProductVariantService;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class ProductVariantService implements StandaloneService
{
    public function __construct(
        protected ProductVariantAssertion $productVariantAssertion,
        protected InventoryInterface $inventoryService,
        protected TransactionalProductVariantService $transactionalProductVariantService,
    ) {}

    public function list(Product $product, ProductVariantFilterDTO $filters): ProductVariantCollection
    {
        $query = $product->variants()->with($this->relations());

        if ($filters->should_manage_stock !== null) {
            $query->where('should_manage_stock', $filters->should_manage_stock);
        }

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $variants = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return ProductVariantCollection::make($variants);
    }

    public function retrieve(Product $product, ProductVariant $variant): ProductVariantResource
    {
        if ($variant->product_id !== $product->id) {
            throw (new ModelNotFoundException())->setModel(ProductVariant::class, [$variant->id]);
        }

        return ProductVariantResource::make($variant->load($this->relations()));
    }

    public function create(Product $product, ProductVariantDTO $dto): AnonymousResourceCollection
    {
        $variants = DB::transaction(
            fn () => $this->transactionalProductVariantService->add($product, $dto->variants)
        );

        $variants->load($this->relations());

        return ProductVariantResource::collection($variants);
    }

    public function update(Product $product, ProductVariant $variant, ProductVariantUpdateDTO $dto): ProductVariantResource
    {
        if ($variant->product_id !== $product->id) {
            throw (new ModelNotFoundException())->setModel(ProductVariant::class, [$variant->id]);
        }

        DB::transaction(function () use ($product, $variant, $dto): void {
            $variant->fill([
                'sku'                 => $dto->sku ?? $variant->sku,
                'should_manage_stock' => $dto->should_manage_stock ?? $variant->should_manage_stock,
                'is_active'           => $dto->is_active ?? $variant->is_active,
            ]);

            $variant->save();

            if ($dto->options !== null) {
                $this->transactionalProductVariantService->replaceOptions($product, $variant, $dto->options);
            }

            $this->inventoryService->updateItem($variant, [
                'sku'       => $variant->sku,
                'is_active' => $variant->should_manage_stock,
                // TODO deduction strategy
            ]);
        });

        return ProductVariantResource::make($variant->load($this->relations()));
    }

    public function delete(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            throw (new ModelNotFoundException())->setModel(ProductVariant::class, [$variant->id]);
        }

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
            'prices.currency',
            'inventoryItem.activeStockLocationSummaries',
        ];
    }
}
