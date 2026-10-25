<?php

declare(strict_types=1);

use Lahatre\Catalog\Http\Resources\BundleItemResource;
use Lahatre\Catalog\Http\Resources\BundleResource;
use Lahatre\Catalog\Http\Resources\BundleStockOperationResource;
use Lahatre\Catalog\Http\Resources\CategoryResource;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Http\Resources\OptionValueResource;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Http\Resources\ServiceResource;
use Lahatre\Catalog\Http\Resources\StockLocationResource;
use Lahatre\Catalog\Http\Resources\StockTransferResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationAnalyzer;

it('declares every catalog Resource and the Resources it includes correctly', function (string $resourceClass): void {
    (new ResponseDeclarationAnalyzer)->validateResource($resourceClass);

    expect($resourceClass::definition()->hasField('id'))->toBeTrue();
})->with([
    BundleResource::class,
    BundleItemResource::class,
    BundleStockOperationResource::class,
    CategoryResource::class,
    OptionResource::class,
    OptionValueResource::class,
    ProductResource::class,
    ProductVariantResource::class,
    ServiceResource::class,
    StockLocationResource::class,
    StockTransferResource::class,
]);

it('does not expose variant labels as a product-level include', function (): void {
    expect(ProductResource::definition()->includeNames())->not->toContain('labels')
        ->and(ProductVariantResource::definition()->includeNames())->toContain('labels');
});

it('renders product options and the category tree only when the client lists them', function (): void {
    expect(ProductResource::definition()->rendersByDefault('options'))->toBeFalse()
        ->and(CategoryResource::definition()->rendersByDefault('bloodline'))->toBeFalse()
        ->and(ProductVariantResource::definition()->rendersByDefault('options'))->toBeTrue();
});
