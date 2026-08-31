<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lahatre\Catalog\Models\ProductVariant;

enum CatalogItemType: string
{
    case ProductVariant = 'product_variant';

    public function isStockable(): bool
    {
        return match ($this->value) {
            self::ProductVariant->value => true,
            default                     => false,
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::ProductVariant => ProductVariant::class,
        };
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

        throw new InvalidArgumentException(sprintf(
            'Unsupported CatalogItem target model [%s].',
            $modelClass,
        ));
    }
}
