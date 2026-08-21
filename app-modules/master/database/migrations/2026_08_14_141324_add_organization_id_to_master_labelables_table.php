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
        Schema::table('master_labelables', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        DB::statement('UPDATE master_labelables AS labelables
            SET organization_id = labels.organization_id
            FROM master_labels AS labels
            WHERE labels.id = labelables.label_id');

        DB::statement('ALTER TABLE master_labelables ALTER COLUMN organization_id SET NOT NULL');

        DB::statement('CREATE UNIQUE INDEX master_labels_organization_id_id_unique
            ON master_labels (organization_id, id)');

        Schema::table('master_labelables', function (Blueprint $table): void {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organization_organizations')
                ->onDelete('restrict');
            $table->foreign(['organization_id', 'label_id'], 'master_labelables_organization_label_id_foreign')
                ->references(['organization_id', 'id'])
                ->on('master_labels')
                ->onDelete('cascade');
            $table->index(
                ['organization_id', 'labelable_type', 'labelable_id'],
                'master_labelables_organization_labelable_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('master_labelables', function (Blueprint $table): void {
            $table->dropForeign('master_labelables_organization_label_id_foreign');
            $table->dropForeign(['organization_id']);
            $table->dropIndex('master_labelables_organization_labelable_index');
            $table->dropColumn('organization_id');
        });

        DB::statement('DROP INDEX IF EXISTS master_labels_organization_id_id_unique');
    }
};
