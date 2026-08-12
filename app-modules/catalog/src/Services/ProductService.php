<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductFilterData;
use Lahatre\Catalog\Http\Resources\ProductCollection;
use Lahatre\Catalog\Http\Resources\ProductResource;
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

    public function list(ProductFilterData $filters): ProductCollection
    {
        $query = Product::query()->where('organization_id', getPermissionsTeamId())->with($this->relations());

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
        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        $products = stableCursorPaginate($query, $filters);

        return ProductCollection::make($products);
    }

    public function retrieve(Product $product): ProductResource
    {
        $product->load($this->relations());

        return ProductResource::make($product);
    }

    public function create(ProductData $data): ProductResource
    {
        $product = new Product;

        $product->fill([
            'organization_id' => getPermissionsTeamId(),
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

        return ProductResource::make($product->load($this->relations()));
    }

    public function update(Product $product, ProductData $data): ProductResource
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

        return ProductResource::make($product->load(['categories']));
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

    /**
     * @return array<int|string, mixed>
     */
    protected function relations(): array
    {
        return [
            'categories',
            'optionValues.option',
            'variants' => function (HasMany $query): void {
                $query->with([
                    'product',
                    'optionValues.option',
                    'unitGroup',
                    'inventoryItem.stockSummaries',
                ]);
            },
        ];
    }
}
