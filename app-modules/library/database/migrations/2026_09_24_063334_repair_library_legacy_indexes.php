<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX library_folders_listing_index');
        DB::statement('CREATE INDEX library_folders_listing_index ON library_folders (organization_id, parent_id, name, id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX library_files_uploaded_by_index ON library_files (uploaded_by) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX library_files_uploaded_by_index');
        DB::statement('DROP INDEX library_folders_listing_index');
        DB::statement('CREATE INDEX library_folders_listing_index ON library_folders (organization_id, parent_id, name, id)');
    }
};
