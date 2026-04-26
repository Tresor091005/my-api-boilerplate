<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('name');
            $table->text('slug');
            $table->text('type')->default('');
            $table->integer('order_col')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX master_tags_organization_id_index ON master_tags (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX master_tags_name_organization_id_type_unique ON master_tags (name, organization_id, type) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE master_tags ADD CONSTRAINT master_tags_name_lowercase_check CHECK (name = lower(name))');

        Schema::create('master_taggables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tag_id')
                ->index()
                ->constrained('master_tags')
                ->onDelete('cascade');
            $table->uuidMorphs('taggable', 'master_taggables_taggable_id_taggable_type_index');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'master_taggables_tag_id_taggable_id_taggable_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_taggables');
        Schema::dropIfExists('master_tags');
    }
};
