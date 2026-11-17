<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\ViewData\SellableItemViewData;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->setUpTaxTenant();
    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    $this->wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
    $this->kg = app(MasterInterface::class)->unit('kg');
    $this->shirt = Product::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Shirt']);
});

/** @param array<string, mixed> $itemAttributes */
function sellableVariant(Product $product, array $itemAttributes = []): ProductVariant
{
    return createCatalogProductVariant(
        ['organization_id' => $product->organization_id, 'product_id' => $product->id],
        ['organization_id' => $product->organization_id, 'unit_group_id' => test()->kg->group_id, ...$itemAttributes],
    );
}

function sellableBundle(string $name, ?string $organizationId = null): Bundle
{
    $item = CatalogItem::factory()->bundle()->create([
        'organization_id' => $organizationId ?? currentOrganizationId(),
        'unit_group_id'   => test()->kg->group_id,
    ]);

    return Bundle::factory()->forCatalogItem($item)->create(['name' => $name]);
}

function sellableService(string $name): Service
{
    $item = CatalogItem::factory()->service()->create([
        'organization_id' => currentOrganizationId(),
        'unit_group_id'   => test()->kg->group_id,
    ]);

    return Service::factory()->forCatalogItem($item)->create(['name' => $name]);
}

/** @param list<string> $taxIds */
function sellablePrice(string $itemId, PriceLevel $level, int $amount, string $unitCode = 'kg', array $taxIds = []): CatalogItemPrice
{
    $price = CatalogItemPrice::factory()->create([
        'organization_id' => currentOrganizationId(),
        'catalog_item_id' => $itemId,
        'price_level_id'  => $level->id,
        'unit_id'         => app(MasterInterface::class)->unit($unitCode)->id,
        'amount'          => $amount,
    ]);

    foreach ($taxIds as $taxId) {
        CatalogItemPriceTax::factory()->create(['catalog_item_price_id' => $price->id, 'tax_id' => $taxId]);
    }

    return $price;
}

/**
 * @param  list<array{0: string, 1: string, 2: string}>  $requests  item id, unit code and price level id
 * @return Collection<string, SellableItemViewData> keyed as the catalog keys them
 */
function sellableItemsFor(string $organizationId, array $requests): Collection
{
    return app(CatalogInterface::class)->sellableItems($organizationId, collect($requests)->map(
        fn (array $request): SellableItemQueryData => SellableItemQueryData::fromArray([
            'catalog_item_id' => $request[0], 'unit_code' => $request[1], 'price_level_id' => $request[2],
        ]),
    ));
}

/**
 * @param  list<array{0: string, 1: string}>  $pairs  item id and unit code
 * @return Collection<string, SellableItemViewData>
 */
function sellableItemsAt(PriceLevel $level, array $pairs): Collection
{
    return sellableItemsFor($level->organization_id, array_map(fn (array $pair): array => [$pair[0], $pair[1], $level->id], $pairs))
        ->keyBy(fn (SellableItemViewData $item): string => "{$item->catalogItemId}:{$item->unitCode}");
}

function giveVariantOption(ProductVariant $variant, Option $option, string $value): void
{
    $optionValue = OptionValue::factory()->create(['organization_id' => $variant->organization_id, 'option_id' => $option->id, 'value' => $value]);

    VariantOptionValue::factory()->create([
        'organization_id' => $variant->organization_id,
        'product_id'      => $variant->product_id,
        'variant_id'      => $variant->id,
        'option_id'       => $option->id,
        'option_value_id' => $optionValue->id,
    ]);
}

it('names a variant after its product and option values in the order given to the variant, a bundle and a service by themselves', function (): void {
    // color created first, to show the variant's own order wins over the options'
    $color = Option::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Color', 'created_at' => now()->subMinutes(2)]);
    $size = Option::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Size', 'created_at' => now()->subMinute()]);
    $withOptions = sellableVariant($this->shirt);
    giveVariantOption($withOptions, $size, 'L');
    giveVariantOption($withOptions, $color, 'Red');
    $withoutOptions = sellableVariant($this->shirt);
    $bundle = sellableBundle('Gift box');
    $service = sellableService('Delivery');

    $items = sellableItemsAt($this->retail, [
        [$withOptions->id, 'kg'], [$withoutOptions->id, 'kg'], [$bundle->id, 'kg'], [$service->id, 'kg'],
    ]);

    expect($items)->toHaveCount(4)
        ->and($items["{$withOptions->id}:kg"]->name)->toBe('Shirt (L, Red)')
        ->and($items["{$withoutOptions->id}:kg"]->name)->toBe('Shirt')
        ->and($items["{$bundle->id}:kg"]->name)->toBe('Gift box')
        ->and($items["{$bundle->id}:kg"]->itemType)->toBe(CatalogItemType::Bundle)
        ->and($items["{$service->id}:kg"]->name)->toBe('Delivery')
        ->and($items["{$service->id}:kg"]->itemType)->toBe(CatalogItemType::Service);
});

it('gives the item, its unit and its price at the level', function (): void {
    $variant = sellableVariant($this->shirt, ['sku' => 'SHIRT-RED', 'is_active' => false]);
    sellablePrice($variant->id, $this->retail, 1500);
    sellablePrice($variant->id, $this->wholesale, 1200);

    $item = sellableItemsAt($this->retail, [[$variant->id, 'kg']])["{$variant->id}:kg"];

    expect($item->catalogItemId)->toBe($variant->id)
        ->and($item->itemType)->toBe(CatalogItemType::ProductVariant)
        ->and($item->sku)->toBe('SHIRT-RED')
        ->and($item->isActive)->toBeFalse()
        ->and($item->unitGroupId)->toBe($this->kg->group_id)
        ->and($item->taxProfileId)->toBeNull()
        ->and($item->unitCode)->toBe('kg')
        ->and($item->unitId)->toBe($this->kg->id)
        ->and($item->unitName)->toBe($this->kg->name)
        ->and($item->unitAmount)->toBe(1500)
        ->and($item->includedTaxIds)->toBe([])
        ->and(sellableItemsAt($this->wholesale, [[$variant->id, 'kg']])["{$variant->id}:kg"]->unitAmount)->toBe(1200);
});

it('leaves the amount empty when there is no price at the level, for the unit, or any more', function (): void {
    $variant = sellableVariant($this->shirt);
    $deletedPrice = sellablePrice($variant->id, $this->retail, 900, 'g');
    sellablePrice($variant->id, $this->wholesale, 1200);
    $deletedPrice->delete();

    $retail = sellableItemsAt($this->retail, [[$variant->id, 'kg'], [$variant->id, 'g']]);

    expect($retail["{$variant->id}:kg"]->unitAmount)->toBeNull()
        ->and($retail["{$variant->id}:kg"]->unitId)->toBe($this->kg->id)
        ->and($retail["{$variant->id}:g"]->unitAmount)->toBeNull()
        ->and($retail["{$variant->id}:g"]->includedTaxIds)->toBe([]);
});

it('gives the taxes a price already contains', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $levy = $this->createPercentageTax('5', ['code' => 'LEVY']);
    $variant = sellableVariant($this->shirt);
    sellablePrice($variant->id, $this->retail, 1180, 'kg', [$vat->id, $levy->id]);
    sellablePrice($variant->id, $this->retail, 2, 'g');

    $items = sellableItemsAt($this->retail, [[$variant->id, 'kg'], [$variant->id, 'g']]);

    expect($items["{$variant->id}:kg"]->includedTaxIds)->toEqualCanonicalizing([$vat->id, $levy->id])
        ->and($items["{$variant->id}:g"]->includedTaxIds)->toBe([])
        ->and($items["{$variant->id}:g"]->unitAmount)->toBe(2);
});

it('gives the tax profile of the item', function (): void {
    $profile = $this->createProfile([['tax_id' => $this->createPercentageTax('18')->id, 'treatment' => 'standard']]);
    $variant = sellableVariant($this->shirt, ['tax_profile_id' => $profile->id]);

    expect(sellableItemsAt($this->retail, [[$variant->id, 'kg']])["{$variant->id}:kg"]->taxProfileId)->toBe($profile->id);
});

it('leaves unit and price empty for a unit outside the unit group or an unknown unit code', function (): void {
    $variant = sellableVariant($this->shirt);
    sellablePrice($variant->id, $this->retail, 1500);

    $items = sellableItemsAt($this->retail, [[$variant->id, 'l'], [$variant->id, 'no-such-unit']]);

    expect($items)->toHaveCount(2);

    foreach (['l', 'no-such-unit'] as $unitCode) {
        $item = $items["{$variant->id}:{$unitCode}"];

        expect($item->unitCode)->toBe($unitCode)
            ->and($item->unitId)->toBeNull()
            ->and($item->unitName)->toBeNull()
            ->and($item->unitAmount)->toBeNull()
            ->and($item->includedTaxIds)->toBe([])
            ->and($item->name)->toBe('Shirt');
    }
});

it('leaves out an unknown item, an item of another organization and a deleted item', function (): void {
    $other = Organization::factory()->create();
    $foreign = createCatalogProductVariant(
        ['organization_id' => $other->id],
        ['organization_id' => $other->id, 'unit_group_id' => $this->kg->group_id],
    );
    $deleted = sellableVariant($this->shirt);
    $kept = sellableVariant($this->shirt);
    $deleted->catalogItem()->firstOrFail()->delete();

    $items = sellableItemsAt($this->retail, [
        [$kept->id, 'kg'], [$deleted->id, 'kg'], [$foreign->id, 'kg'], [(string) Str::uuid7(), 'kg'], ['not-a-uuid', 'kg'],
    ]);

    expect($items->keys()->all())->toBe(["{$kept->id}:kg"]);
});

it('answers each request at its own level, keyed by item, unit and level', function (): void {
    $variant = sellableVariant($this->shirt);
    sellablePrice($variant->id, $this->retail, 1500);
    sellablePrice($variant->id, $this->wholesale, 1200);

    $items = sellableItemsFor($this->organization->id, [
        [$variant->id, 'kg', $this->retail->id],
        [$variant->id, 'kg', $this->wholesale->id],
    ]);
    $retail = $items["{$variant->id}:kg:{$this->retail->id}"];
    $wholesale = $items["{$variant->id}:kg:{$this->wholesale->id}"];

    expect($items)->toHaveCount(2)
        ->and($retail->unitAmount)->toBe(1500)
        ->and($retail->priceLevelId)->toBe($this->retail->id)
        ->and($retail->priceLevelName)->toBe('retail')
        ->and($retail->currencyCode)->toBe($this->retail->currency_code)
        ->and($wholesale->unitAmount)->toBe(1200)
        ->and($wholesale->priceLevelName)->toBe('wholesale');
});

it('leaves level and price empty for a deleted, foreign or unknown level', function (): void {
    $variant = sellableVariant($this->shirt);
    sellablePrice($variant->id, $this->wholesale, 1200);
    $this->wholesale->delete();
    $foreignLevel = PriceLevel::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $unknownLevelId = (string) Str::uuid7();

    $items = sellableItemsFor($this->organization->id, [
        [$variant->id, 'kg', $this->wholesale->id],
        [$variant->id, 'kg', $foreignLevel->id],
        [$variant->id, 'kg', $unknownLevelId],
    ]);

    expect($items)->toHaveCount(3);

    foreach ([$this->wholesale->id, $foreignLevel->id, $unknownLevelId] as $levelId) {
        $item = $items["{$variant->id}:kg:{$levelId}"];

        expect($item->priceLevelId)->toBe($levelId)
            ->and($item->priceLevelName)->toBeNull()
            ->and($item->currencyCode)->toBeNull()
            ->and($item->unitAmount)->toBeNull()
            ->and($item->name)->toBe('Shirt');
    }
});

it('returns nothing for no request', function (): void {
    expect(sellableItemsAt($this->retail, []))->toBeEmpty();
});

it('runs as many queries for 12 requests as for 2', function (): void {
    $vat = $this->createPercentageTax('18', ['code' => 'VAT']);
    $color = Option::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Color']);
    $pairs = [];

    foreach (range(1, 6) as $index) {
        $variant = sellableVariant($this->shirt);
        giveVariantOption($variant, $color, "Color {$index}");
        sellablePrice($variant->id, $this->retail, 1180 + $index, 'kg', [$vat->id]);
        $bundle = sellableBundle("Box {$index}");
        sellablePrice($bundle->id, $this->retail, 5000 + $index);
        $pairs[] = [$variant->id, 'kg'];
        $pairs[] = [$bundle->id, 'kg'];
    }

    $countQueries = function (array $requested) use ($pairs, $vat): int {
        app()->forgetScopedInstances();
        cache()->flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $items = sellableItemsAt($this->retail, $requested);
        DB::disableQueryLog();

        expect($items)->toHaveCount(count($requested))
            ->and($items->first()->name)->toBe('Shirt (Color 1)')
            ->and($items->first()->includedTaxIds)->toBe([$vat->id])
            ->and($items->get("{$pairs[1][0]}:kg")->unitAmount)->toBe(5001);

        return count(DB::getQueryLog());
    };

    expect($countQueries($pairs))->toBe($countQueries(array_slice($pairs, 0, 2)));
});
