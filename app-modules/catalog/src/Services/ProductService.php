<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\ProductAssertion;
use Lahatre\Catalog\DTO\ProductDTO;
use Lahatre\Catalog\DTO\ProductFilterDTO;
use Lahatre\Catalog\Http\Resources\ProductCollection;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\Variant\ProductVariantService;
use Lahatre\Shared\Contracts\Services\StandaloneService;
use Lahatre\Shared\Support\HandleGenerator;

class ProductService implements StandaloneService
{
    protected array $relations = [
        'categories',
        'optionValues.option',
        'variants' => [
            'product',
            'optionValues.option',
            'unitGroup',
            'prices.currency', // TODO add prices
        ],
    ];

    public function __construct(
        protected ProductAssertion $productAssertion,
        protected ProductVariantService $productVariantService
    ) {}

    public function list(ProductFilterDTO $filters): ProductCollection
    {
        $query = Product::query()->with($this->relations);

        // TODO category filter

        if ($filters->handle) {
            $query->where('handle', 'like', "$filters->handle%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }
        if ($filters->description) {
            $query->where('description', 'like', "$filters->description%");
        }
        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $products = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return ProductCollection::make($products);
    }

    public function retrieve(Product $product): ProductResource
    {
        $product->load($this->relations);

        return ProductResource::make($product);
    }

    public function create(ProductDTO $dto): ProductResource
    {
        $product = new Product();

        $product->fill([
            'name'        => $dto->name,
            'description' => $dto->description,
            'is_active'   => $dto->is_active,
        ]);

        $product->handle = HandleGenerator::generate(
            $dto->name,
            $product->getTable()
        );

        DB::transaction(function () use ($product, $dto): void {
            $product->save();

            $product->categories()->sync($dto->categories ?? []);

            $this->productVariantService->add($product, $dto->variants ?? collect());
        });

        return ProductResource::make($product->load($this->relations));
    }

    public function update(Product $product, ProductDTO $dto): ProductResource
    {
        $product->fill([
            'name'        => $dto->name,
            'description' => $dto->description,
            'is_active'   => $dto->is_active,
        ]);

        DB::transaction(function () use ($product, $dto): void {
            $product->save();
            if ($dto->categories !== null) {
                $product->categories()->sync($dto->categories);
            }
        });

        return ProductResource::make($product->load(['categories']));
    }

    public function delete(Product $product): void
    {
        // TODO: Implementation with assertions and cleanup
    }
}
