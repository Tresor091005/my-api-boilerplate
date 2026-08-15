<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductFilterData;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Variant\TransactionalProductVariantService;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

class ProductService
{
    public function __construct(
        protected TransactionalProductVariantService $transactionalProductVariantService
    ) {}

    public function paginate(ProductFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->productsQuery($filters)),
            $filters,
        );
    }

    /** @return Builder<Product> */
    private function productsQuery(ProductFilterData $filters): Builder
    {
        $query = Product::query()->where('organization_id', currentOrganizationId());

        if ($filters->handle) {
            $query->where('handle', 'like', "$filters->handle%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }
        if ($filters->description) {
            $query->where('description', 'like', "$filters->description%");
        }
        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        return $query;
    }

    public function retrieve(Product $product): Product
    {
        $product->load(responseRelationsToLoad());

        return $product;
    }

    public function create(ProductData $data): Product
    {
        $product = new Product;

        $product->fill([
            'organization_id' => currentOrganizationId(),
            'name'            => required($data->name),
            'description'     => required($data->description),
            'is_active'       => required($data->isActive),
        ]);

        $product->handle = HandleGenerator::generate(
            required($data->name),
            $product->getTable(),
            extra: ['organization_id' => $product->organization_id]
        );

        DB::transaction(function () use ($product, $data): void {
            $product->save();

            $product->categories()->sync(required($data->categories) ?? []);

            $this->transactionalProductVariantService->createMany($product, required($data->variants));
        });

        return $product->load(responseRelationsToLoad());
    }

    public function update(Product $product, ProductData $data): Product
    {
        $product->fill(withoutMissing([
            'name'        => $data->name,
            'description' => $data->description,
            'is_active'   => $data->isActive,
        ]));

        DB::transaction(function () use ($product, $data): void {
            $product->save();
            if (!$data->categories instanceof MissingValue) {
                $product->categories()->sync($data->categories ?? []);
            }
        });

        return $product->load(responseRelationsToLoad());
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            /** @var Collection<int, ProductVariant> $variants */
            $variants = $product->variants()->get();
            foreach ($variants as $variant) {
                $this->transactionalProductVariantService->delete($variant);
            }

            $product->delete();
        });
    }
}
