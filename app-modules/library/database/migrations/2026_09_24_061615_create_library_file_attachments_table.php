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
        Schema::create('library_file_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('file_id');
            $table->string('attachable_type', 100);
            $table->uuid('attachable_id');
            $table->string('slot', 50);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->foreign(['organization_id', 'file_id'], 'library_attachments_organization_file_foreign')
                ->references(['organization_id', 'id'])->on('library_files')->restrictOnDelete();
            $table->index(['organization_id', 'attachable_type', 'attachable_id', 'slot', 'position', 'id'], 'library_attachments_parent_listing_index');
            $table->index(['organization_id', 'file_id'], 'library_attachments_file_index');
            $table->unique(['organization_id', 'attachable_type', 'attachable_id', 'slot', 'file_id'], 'library_attachments_parent_slot_file_unique');
        });

        DB::statement('ALTER TABLE library_file_attachments ADD CONSTRAINT library_attachments_position_positive CHECK (position > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('library_file_attachments');
    }
};
