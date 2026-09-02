<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\ProductVariant;

enum CatalogItemType: string
{
    case ProductVariant = 'catalog_product_variant';
    case Bundle = 'catalog_bundle';

    public function isStockable(): bool
    {
        return match ($this) {
            self::Bundle,
            self::ProductVariant => true,
        };
    }

    /** @return list<self> */
    public static function allowedBundleComponentTypes(): array
    {
        return [self::ProductVariant];
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::ProductVariant => ProductVariant::class,
            self::Bundle         => Bundle::class,
        };
    }

    public function morphAlias(): string
    {
        return (new ($this->modelClass()))->getMorphClass();
    }

    /**
     * @param  Model|class-string<Model>  $model
     *
     * @throws InvalidArgumentException When the model is not a supported CatalogItem target.
     */
    public static function fromModel(Model|string $model): self
    {
        $modelClass = $model instanceof Model ? $model::class : $model;

        foreach (self::cases() as $type) {
            $targetClass = $type->modelClass();

            if (is_a($modelClass, $targetClass, true)) {
                return $type;
            }
        }

        throw new InvalidArgumentException(
            __('catalog::exceptions.unsupported_catalog_item_target_model', [
                'class' => $modelClass,
            ])
        );
    }
}
