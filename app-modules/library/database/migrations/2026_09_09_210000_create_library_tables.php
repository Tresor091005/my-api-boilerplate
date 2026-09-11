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
        Schema::create('library_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'library_folders_organization_id_id_unique');
            $table->index(['organization_id', 'parent_id', 'name', 'id'], 'library_folders_listing_index');
        });

        Schema::table('library_folders', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'parent_id'], 'library_folders_organization_parent_foreign')
                ->references(['organization_id', 'id'])
                ->on('library_folders')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX library_folders_root_name_unique
            ON library_folders (organization_id, name)
            WHERE parent_id IS NULL AND deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX library_folders_child_name_unique
            ON library_folders (organization_id, parent_id, name)
            WHERE parent_id IS NOT NULL AND deleted_at IS NULL
            SQL);
        DB::statement('CREATE INDEX library_folders_deleted_at_index ON library_folders (deleted_at)');

        Schema::create('library_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('folder_id')->nullable();
            $table->string('name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->string('extension', 20)->nullable();
            $table->bigInteger('size');
            $table->string('storage_disk', 50);
            $table->text('storage_key');
            $table->char('checksum', 64);
            $table->uuid('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'library_files_organization_id_id_unique');
            $table->unique(
                ['organization_id', 'storage_disk', 'storage_key'],
                'library_files_storage_object_unique',
            );
            $table->index('deleted_at', 'library_files_deleted_at_index');
        });

        Schema::table('library_files', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'folder_id'], 'library_files_organization_folder_foreign')
                ->references(['organization_id', 'id'])
                ->on('library_folders')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE library_files
            ADD CONSTRAINT library_files_size_non_negative CHECK (size >= 0)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX library_files_listing_index
            ON library_files (organization_id, folder_id, name, id)
            WHERE deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX library_files_mime_index
            ON library_files (organization_id, mime_type)
            WHERE deleted_at IS NULL
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
        Schema::dropIfExists('library_folders');
    }
};
