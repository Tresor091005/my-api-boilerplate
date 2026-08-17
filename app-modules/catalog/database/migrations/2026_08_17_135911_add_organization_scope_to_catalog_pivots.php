<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_product_categories', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->nullable()->after('id');
        });

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->nullable()->after('id');
        });

        DB::statement(<<<'SQL'
            UPDATE catalog_product_categories AS product_categories
            SET organization_id = products.organization_id
            FROM catalog_products AS products
            WHERE products.id = product_categories.product_id
            SQL);

        DB::statement(<<<'SQL'
            UPDATE catalog_variant_option_value AS variant_options
            SET organization_id = products.organization_id
            FROM catalog_products AS products
            WHERE products.id = variant_options.product_id
            SQL);

        $invalidProductCategories = DB::table('catalog_product_categories as product_categories')
            ->join('catalog_products as products', 'products.id', '=', 'product_categories.product_id')
            ->join('catalog_categories as categories', 'categories.id', '=', 'product_categories.category_id')
            ->whereColumn('products.organization_id', '!=', 'categories.organization_id')
            ->exists();

        if ($invalidProductCategories) {
            throw new RuntimeException('Catalog product categories contain cross-organization links.');
        }

        $invalidVariantOptions = DB::table('catalog_variant_option_value as variant_options')
            ->join('catalog_products as products', 'products.id', '=', 'variant_options.product_id')
            ->join('catalog_product_variants as variants', 'variants.id', '=', 'variant_options.variant_id')
            ->join('catalog_options as options', 'options.id', '=', 'variant_options.option_id')
            ->join('catalog_option_values as option_values', 'option_values.id', '=', 'variant_options.option_value_id')
            ->where(function ($query): void {
                $query
                    ->whereColumn('products.organization_id', '!=', 'variants.organization_id')
                    ->orWhereColumn('products.organization_id', '!=', 'options.organization_id')
                    ->orWhereColumn('products.organization_id', '!=', 'option_values.organization_id');
            })
            ->exists();

        if ($invalidVariantOptions) {
            throw new RuntimeException('Catalog variant option links contain cross-organization links.');
        }

        Schema::table('catalog_product_categories', function (Blueprint $table): void {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organization_organizations')
                ->restrictOnDelete();
        });

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organization_organizations')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE catalog_product_categories ALTER COLUMN organization_id SET NOT NULL');
        DB::statement('ALTER TABLE catalog_variant_option_value ALTER COLUMN organization_id SET NOT NULL');

        foreach ([
            'catalog_categories',
            'catalog_products',
            'catalog_options',
            'catalog_option_values',
            'catalog_product_variants',
        ] as $tableName) {
            DB::statement("CREATE UNIQUE INDEX {$tableName}_organization_id_id_unique ON {$tableName} (organization_id, id)");
        }

        Schema::table('catalog_product_categories', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['category_id']);
            $table->foreign(['organization_id', 'product_id'], 'catalog_product_categories_organization_product_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_products')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'category_id'], 'catalog_product_categories_organization_category_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_categories')
                ->cascadeOnDelete();
            $table->dropUnique('catalog_product_categories_category_id_product_id_unique');
            $table->unique(
                ['organization_id', 'category_id', 'product_id'],
                'catalog_product_categories_organization_category_product_unique'
            );
        });

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['variant_id']);
            $table->dropForeign(['option_id']);
            $table->dropForeign(['option_value_id']);
            $table->foreign(['organization_id', 'product_id'], 'catalog_variant_options_organization_product_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_products')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'variant_id'], 'catalog_variant_options_organization_variant_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_product_variants')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'option_id'], 'catalog_variant_options_organization_option_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_options')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'option_value_id'], 'catalog_variant_options_organization_option_value_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_option_values')
                ->cascadeOnDelete();
            $table->dropUnique('catalog_variant_option_value_option_id_variant_id_unique');
            $table->unique(
                ['organization_id', 'option_id', 'variant_id'],
                'catalog_variant_options_organization_option_variant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('catalog_product_categories', function (Blueprint $table): void {
            $table->dropForeign('catalog_product_categories_organization_product_foreign');
            $table->dropForeign('catalog_product_categories_organization_category_foreign');
            $table->dropUnique('catalog_product_categories_organization_category_product_unique');
            $table->foreign('product_id')
                ->references('id')
                ->on('catalog_products')
                ->cascadeOnDelete();
            $table->foreign('category_id')
                ->references('id')
                ->on('catalog_categories')
                ->cascadeOnDelete();
            $table->unique(['category_id', 'product_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->dropForeign('catalog_variant_options_organization_product_foreign');
            $table->dropForeign('catalog_variant_options_organization_variant_foreign');
            $table->dropForeign('catalog_variant_options_organization_option_foreign');
            $table->dropForeign('catalog_variant_options_organization_option_value_foreign');
            $table->dropUnique('catalog_variant_options_organization_option_variant_unique');
            $table->foreign('product_id')
                ->references('id')
                ->on('catalog_products')
                ->cascadeOnDelete();
            $table->foreign('variant_id')
                ->references('id')
                ->on('catalog_product_variants')
                ->cascadeOnDelete();
            $table->foreign('option_id')
                ->references('id')
                ->on('catalog_options')
                ->cascadeOnDelete();
            $table->foreign('option_value_id')
                ->references('id')
                ->on('catalog_option_values')
                ->cascadeOnDelete();
            $table->unique(['option_id', 'variant_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        foreach ([
            'catalog_categories',
            'catalog_products',
            'catalog_options',
            'catalog_option_values',
            'catalog_product_variants',
        ] as $tableName) {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_organization_id_id_unique");
        }
    }
};
