<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(['catalog_product.delete']);
    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
});

/** A product whose variants each have an inventory item and one price. */
function productWithVariants(int $variantCount, PriceLevel $priceLevel): Product
{
    $product = Product::factory()->create(['organization_id' => currentOrganizationId()]);
    $unit = app(MasterInterface::class)->unit('kg');

    foreach (range(1, $variantCount) as $ignored) {
        $variant = createCatalogProductVariant([
            'organization_id' => currentOrganizationId(),
            'product_id'      => $product->id,
        ], [
            'organization_id' => currentOrganizationId(),
            'unit_group_id'   => $unit->group_id,
        ]);
        app(InventoryInterface::class)->createItem(CatalogItem::query()->findOrFail($variant->id));
        CatalogItemPrice::factory()->create([
            'catalog_item_id' => $variant->id,
            'price_level_id'  => $priceLevel->id,
            'unit_id'         => $unit->id,
            'amount'          => 1000,
        ]);
    }

    return $product;
}

/** @return Collection<int, string> */
function variantIdsOf(Product $product): Collection
{
    return ProductVariant::withTrashed()->where('product_id', $product->id)->orderBy('id')->pluck('id');
}

/** @param Collection<int, string> $variantIds */
function expectVariantsKept(Product $product, Collection $variantIds): void
{
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(ProductVariant::query()->whereIn('id', $variantIds)->count())->toBe($variantIds->count())
        ->and(CatalogItem::query()->whereIn('id', $variantIds)->count())->toBe($variantIds->count())
        ->and(CatalogItemPrice::query()->whereIn('catalog_item_id', $variantIds)->count())->toBe($variantIds->count())
        ->and(InventoryItem::query()->whereIn('itemable_id', $variantIds)->count())->toBe($variantIds->count());
}

it('deletes a product with its variants, their items, prices and inventory items, with as many queries for many variants as for a few', function (): void {
    $countQueries = function (int $variantCount): int {
        $product = productWithVariants($variantCount, $this->retail);
        $variantIds = variantIdsOf($product);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->deleteJson("/v1/catalog/products/{$product->id}")->assertNoContent();
        DB::disableQueryLog();

        expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
            ->and(ProductVariant::query()->whereIn('id', $variantIds)->count())->toBe(0)
            ->and(ProductVariant::withTrashed()->whereIn('id', $variantIds)->count())->toBe($variantCount)
            ->and(CatalogItem::query()->whereIn('id', $variantIds)->count())->toBe(0)
            ->and(CatalogItem::withTrashed()->whereIn('id', $variantIds)->count())->toBe($variantCount)
            ->and(CatalogItemPrice::query()->whereIn('catalog_item_id', $variantIds)->count())->toBe(0)
            ->and(InventoryItem::query()->whereIn('itemable_id', $variantIds)->count())->toBe(0);

        return count(DB::getQueryLog());
    };

    // the first call also fills what the next ones reuse
    $countQueries(1);

    expect($countQueries(10))->toBe($countQueries(2));
});

it('deletes nothing of a product when one of its variants still holds stock', function (): void {
    $product = productWithVariants(3, $this->retail);
    $variantIds = variantIdsOf($product);
    $stockedItem = InventoryItem::query()->where('itemable_id', $variantIds->last())->firstOrFail();
    InventoryStock::factory()->for($stockedItem, 'item')->create(['organization_id' => $this->organization->id, 'remaining' => 1]);

    $this->deleteJson("/v1/catalog/products/{$product->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('inventory::exceptions.item_cannot_delete_with_active_stock'))
        ->assertJsonPath('errors.context.item_id', $stockedItem->id);

    expectVariantsKept($product, $variantIds);
});

it('deletes nothing of a product when a bundle uses one of its variants', function (): void {
    $product = productWithVariants(3, $this->retail);
    $variantIds = variantIdsOf($product);
    $bundleCatalogItem = CatalogItem::factory()->create([
        'organization_id' => $this->organization->id,
        'item_type'       => CatalogItemType::Bundle,
    ]);
    BundleItem::factory()->create([
        'organization_id'   => $this->organization->id,
        'bundle_id'         => Bundle::factory()->forCatalogItem($bundleCatalogItem)->create(['organization_id' => $this->organization->id])->id,
        'item_id'           => $variantIds->first(),
        'display_unit_code' => 'kg',
    ]);

    $this->deleteJson("/v1/catalog/products/{$product->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::exceptions.catalog_item_used_by_bundle'))
        ->assertJsonPath('errors.context.catalog_item_id', $variantIds->first());

    expectVariantsKept($product, $variantIds);
});
