<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fails if two live variants of a product already share their option combination. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->nullable();
        });

        DB::statement('UPDATE catalog_variant_option_value AS link SET position = ranked.position
            FROM (SELECT id, row_number() OVER (PARTITION BY variant_id ORDER BY id) AS position FROM catalog_variant_option_value) AS ranked
            WHERE link.id = ranked.id');

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->unsignedSmallInteger('position')->nullable(false)->change();
            $table->unique(['organization_id', 'variant_id', 'position'], 'catalog_variant_option_value_position_unique');
        });

        DB::statement('ALTER TABLE catalog_variant_option_value ADD CONSTRAINT catalog_variant_option_value_position_positive CHECK (position > 0)');

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->char('option_signature', 64)->nullable();
        });

        DB::statement("UPDATE catalog_product_variants AS variant SET option_signature = encode(sha256(convert_to(coalesce((
                SELECT string_agg(link.option_value_id::text, ',' ORDER BY link.option_value_id::text COLLATE \"C\")
                FROM catalog_variant_option_value AS link
                WHERE link.variant_id = variant.id
            ), ''), 'UTF8')), 'hex')");

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->char('option_signature', 64)->nullable(false)->change();
        });

        DB::statement('CREATE UNIQUE INDEX catalog_product_variants_option_signature_unique ON catalog_product_variants (organization_id, product_id, option_signature) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_option_signature_unique');

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropColumn('option_signature');
        });

        DB::statement('ALTER TABLE catalog_variant_option_value DROP CONSTRAINT IF EXISTS catalog_variant_option_value_position_positive');

        Schema::table('catalog_variant_option_value', function (Blueprint $table): void {
            $table->dropUnique('catalog_variant_option_value_position_unique');
            $table->dropColumn('position');
        });
    }
};
