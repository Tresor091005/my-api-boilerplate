<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_labels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('value');
            $table->text('slug');
            $table->text('group')->default('');
            $table->integer('order_col')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX master_labels_organization_id_group_order_col_index ON master_labels (organization_id, "group", order_col) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX master_labels_value_organization_id_group_unique ON master_labels (value, organization_id, "group") WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE master_labels ADD CONSTRAINT master_labels_value_lowercase_check CHECK (value = lower(value))');

        Schema::create('master_labelables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('label_id')
                ->index()
                ->constrained('master_labels')
                ->onDelete('cascade');
            $table->uuidMorphs('labelable', 'master_labelables_labelable_type_labelable_id_index');
            $table->unique(['label_id', 'labelable_type', 'labelable_id'], 'master_labelables_label_id_labelable_type_labelable_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_labelables');
        Schema::dropIfExists('master_labels');
    }
};
