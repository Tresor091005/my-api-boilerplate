<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Lahatre\Catalog\Assertions\ProductAssertion;
use Lahatre\Catalog\DTO\ProductDTO;
use Lahatre\Catalog\DTO\ProductFilterDTO;
use Lahatre\Catalog\Http\Resources\ProductCollection;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class ProductService implements StandaloneService
{
    public function __construct(
        protected ProductAssertion $productAssertion
    ) {}

    public function list(ProductFilterDTO $filters): ProductCollection
    {
        $query = Product::query()->with([
            'categories', 'optionValues.option',
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
            'optionValues.option',
            'variants' => [
                'product',
                'optionValues.option',
                'unit',
                'prices.currency',
            ],
        ]);

        return ProductResource::make($product);
    }

    public function create(ProductDTO $dto): ProductResource
    {
        //
    }

    public function update(Product $category, ProductDTO $dto): ProductResource
    {
        //
    }

    public function delete(Product $category): void
    {
        //
    }
}
