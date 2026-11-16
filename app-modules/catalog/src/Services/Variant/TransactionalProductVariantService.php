<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Assertions\ProductVariantAssertion;
use Lahatre\Catalog\Data\CatalogItemData;
use Lahatre\Catalog\Data\ProductVariantData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Catalog\Services\TransactionalCatalogItemService;

class TransactionalProductVariantService
{
    public function __construct(
        protected TransactionalCatalogItemService $transactionalCatalogItemService,
        protected TransactionalOptionService $transactionalOptionService,
        protected ProductVariantAssertion $productVariantAssertion,
    ) {}

    /**
     * Creates variants of a product, each with its option values in the order
     * they were sent. The caller owns the surrounding transaction.
     *
     * @param  Collection<int, ProductVariantData>  $variantsData
     *
     * @throws ProductVariantException If two variants of the product would share their combination of option values.
     *
     * @return EloquentCollection<int, ProductVariant>
     */
    public function createMany(Product $product, Collection $variantsData): EloquentCollection
    {
        if ($variantsData->isEmpty()) {
            return new EloquentCollection;
        }

        $variantsData = $variantsData->values();
        $optionValues = $this->resolveOptionValues($product, $variantsData->map(fn (ProductVariantData $variantData): array => $variantData->options));
        $combinations = $this->combinations($variantsData->map(fn (ProductVariantData $variantData): array => $variantData->options), $optionValues);
        $this->productVariantAssertion->assertOptionCombinationsAvailable(
            $product,
            $combinations,
            $this->takenSignatures($product, $combinations->pluck('signature')),
        );

        $catalogItems = $this->transactionalCatalogItemService->createManyProductVariants(
            $product->organization_id,
            $product->name,
            $variantsData->map(
                fn (ProductVariantData $variantData): CatalogItemData => $variantData->catalogItem(),
            ),
        );

        $variantRows = collect();
        $variantOptionValues = collect();
        $variantDataWithCatalogItems = $variantsData->zip($catalogItems->values());

        foreach ($variantDataWithCatalogItems as $index => $pair) {
            /** @var CatalogItem $catalogItem */
            $catalogItem = $pair[1];

            $variantRows->push([
                'id'               => $catalogItem->id,
                'organization_id'  => $product->organization_id,
                'product_id'       => $product->id,
                'option_signature' => $combinations[$index]['signature'],
                'created_at'       => $catalogItem->created_at,
                'updated_at'       => $catalogItem->updated_at,
            ]);
            $variantOptionValues->put($catalogItem->id, $optionValues[$index]);
        }

        $this->guardSignatures($product, $combinations, fn (): bool => ProductVariant::insert($variantRows->all()));

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->where('organization_id', $product->organization_id)
            ->whereIn('id', $variantRows->pluck('id')->all())
            ->get();

        $this->attachOptionValues($product, $variantOptionValues);

        $variantsById = $variants->keyBy('id');
        foreach ($variantDataWithCatalogItems as $pair) {
            /** @var ProductVariantData $variantData */
            $variantData = $pair[0];
            /** @var CatalogItem $catalogItem */
            $catalogItem = $pair[1];

            if ($variantData->labels === []) {
                continue;
            }

            $variantsById->get($catalogItem->id)?->attachLabels($variantData->labels);
        }

        return $variants;
    }

    /**
     * Replaces the option values of a variant, kept in the order they were
     * sent. The caller owns the surrounding transaction.
     *
     * @param  array<int, array{name: string, value: string}>  $optionsData
     *
     * @throws ProductVariantException If another variant of the product already has this combination of option values.
     */
    public function replaceOptions(Product $product, ProductVariant $variant, array $optionsData): void
    {
        $optionValues = $this->resolveOptionValues($product, collect([$optionsData]));
        $combinations = $this->combinations(collect([$optionsData]), $optionValues);
        $this->productVariantAssertion->assertOptionCombinationsAvailable(
            $product,
            $combinations,
            $this->takenSignatures($product, $combinations->pluck('signature'), $variant->id),
        );

        VariantOptionValue::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->delete();

        $this->guardSignatures($product, $combinations, fn (): int => ProductVariant::query()
            ->where('organization_id', $product->organization_id)
            ->whereKey($variant->id)
            ->update(['option_signature' => $combinations[0]['signature']]));
        $variant->option_signature = $combinations[0]['signature'];

        $this->attachOptionValues($product, collect([$variant->id => $optionValues[0]]));
    }

    public function delete(ProductVariant $variant): void
    {
        $this->deleteMany($variant->product_id, new EloquentCollection([$variant]));
    }

    /**
     * Delete variants of one product of the current organization with their
     * option values, catalog items, prices and inventory items, all or none,
     * with a fixed number of queries whatever their count.
     *
     * The caller owns the surrounding transaction.
     *
     * @param  EloquentCollection<int, ProductVariant>  $variants  variants of that product
     *
     * @throws ModelNotFoundException If a variant has no catalog item.
     */
    public function deleteMany(string $productId, EloquentCollection $variants): void
    {
        if ($variants->isEmpty()) {
            return;
        }

        $organizationId = currentOrganizationId();
        $variantIds = $variants->pluck('id')->unique()->values();

        VariantOptionValue::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereIn('variant_id', $variantIds)
            ->delete();

        // a variant and its catalog item share their id
        $catalogItems = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->where('item_type', CatalogItemType::ProductVariant->value)
            ->whereIn('id', $variantIds)
            ->get();
        $missingIds = $variantIds->diff($catalogItems->pluck('id'))->values()->all();

        if ($missingIds !== []) {
            throw new ModelNotFoundException()->setModel(CatalogItem::class, $missingIds);
        }

        ProductVariant::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereIn('id', $variantIds)
            ->delete();
        $this->transactionalCatalogItemService->deleteMany($catalogItems);
    }

    /**
     * The option values of each variant, in the order they were sent; an
     * option value is created when missing.
     *
     * @param  Collection<int, array<int, array{name: string, value: string}>>  $variantsOptions
     *
     * @throws ProductVariantException If an option value was deleted meanwhile.
     *
     * @return Collection<int, Collection<int, OptionValue>>
     */
    protected function resolveOptionValues(Product $product, Collection $variantsOptions): Collection
    {
        $optionValuesMap = $this->transactionalOptionService->resolveOrCreateValues($variantsOptions->flatten(1));
        $find = fn (array $optionData): ?OptionValue => $optionValuesMap->get(
            TransactionalOptionService::valueKey($optionData['name'], $optionData['value']),
        );
        $missing = $variantsOptions->flatten(1)->reject(fn (array $optionData): bool => $find($optionData) !== null);

        if ($missing->isNotEmpty()) {
            throw ProductVariantException::optionsUnavailable($product, $missing->unique()->values()->all());
        }

        return $variantsOptions->map(fn (array $options): Collection => collect($options)
            ->map(fn (array $optionData): OptionValue => $optionValuesMap->get(TransactionalOptionService::valueKey($optionData['name'], $optionData['value'])))
            ->values());
    }

    /**
     * @param  Collection<int, array<int, array{name: string, value: string}>>  $variantsOptions
     * @param  Collection<int, Collection<int, OptionValue>>  $optionValues
     * @return Collection<int, array{signature: string, options: array<int, array{name: string, value: string}>}>
     */
    protected function combinations(Collection $variantsOptions, Collection $optionValues): Collection
    {
        return $variantsOptions->map(fn (array $options, int $index): array => [
            'signature' => ProductVariant::optionSignature($optionValues[$index]->pluck('id')),
            'options'   => $options,
        ]);
    }

    /**
     * @param  Collection<int, string>  $signatures
     * @return Collection<int, string>
     */
    protected function takenSignatures(Product $product, Collection $signatures, ?string $exceptVariantId = null): Collection
    {
        return ProductVariant::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->whereIn('option_signature', $signatures->unique()->values())
            ->when($exceptVariantId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->pluck('option_signature');
    }

    /**
     * Runs a write a simultaneous variant may race; the partial unique index
     * is the last guard and its refusal reads as the business failure.
     *
     * @template TResult
     *
     * @param  Collection<int, array{signature: string, options: array<int, array{name: string, value: string}>}>  $combinations
     * @param  callable(): TResult  $write
     *
     * @throws ProductVariantException If another variant of the product took a combination meanwhile.
     *
     * @return TResult
     */
    protected function guardSignatures(Product $product, Collection $combinations, callable $write): mixed
    {
        try {
            return $write();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505' && str_contains($exception->getMessage(), 'catalog_product_variants_option_signature_unique')) {
                throw ProductVariantException::optionCombinationTaken($product, $combinations->pluck('options')->values()->all());
            }

            throw $exception;
        }
    }

    /**
     * @param  Collection<string, Collection<int, OptionValue>>  $variantOptionValues  keyed by variant id
     */
    protected function attachOptionValues(Product $product, Collection $variantOptionValues): void
    {
        $pivotRows = $variantOptionValues->flatMap(fn (Collection $optionValues, string $variantId): array => $optionValues
            ->values()
            ->map(fn (OptionValue $optionValue, int $index): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $product->organization_id,
                'product_id'      => $product->id,
                'variant_id'      => $variantId,
                'option_id'       => $optionValue->option_id,
                'option_value_id' => $optionValue->id,
                'position'        => $index + 1,
            ])
            ->all());

        if ($pivotRows->isNotEmpty()) {
            VariantOptionValue::insert($pivotRows->all());
        }
    }
}
