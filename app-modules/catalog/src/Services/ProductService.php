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
use Lahatre\Shared\Support\HandleGenerator;

class ProductService
{
    public function __construct(
        protected ProductAssertion $productAssertion
    ) {}

    public function list(ProductFilterDTO $filters): ProductCollection
    {
        $query = Product::query()->with([
            'categories', 'tags', 'optionValues.option',
        ]);

        if ($filters->handle) {
            $query->where('handle', 'like', "%{$filters->handle}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }
        if ($filters->description) {
            $query->where('description', 'like', "%{$filters->description}%");
        }
        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $categories = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return ProductCollection::make($categories);
    }

    public function retrieve(Product $product): ProductResource
    {
        $product->load([
            'categories',
            'tags',
            'optionValues.option',
            'variants' => [
                'product',
                'optionValues.option',
                'unit',
                'prices.currency'
            ],
        ]);

        return ProductResource::make($product);
    }

    public function create(ProductDTO $dto): ProductResource
    {
        $category = new Product();

        $category->fill([
            'name'      => $dto->name,
            'parent_id' => $dto->parent_id,
            'is_active' => $dto->is_active,
        ]);

        $category->handle = HandleGenerator::generate(
            $dto->name,
            $category->getTable()
        );

        DB::transaction(fn () => $category->save());

        return ProductResource::make($category->load(['bloodline']));
    }

    public function update(Product $category, ProductDTO $dto): ProductResource
    {
        $category->fill([
            'name'      => $dto->name,
            'parent_id' => $dto->parent_id,
            'is_active' => $dto->is_active,
        ]);

        DB::transaction(fn () => $category->save());

        return ProductResource::make($category->load(['bloodline']));
    }

    public function delete(Product $category): void
    {
        DB::transaction(function () use ($category): void {
            $category->products()->sync([]);
            $category->delete();
        });
    }
}
