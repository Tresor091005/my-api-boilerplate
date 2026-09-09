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
        Schema::create('master_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('notable_type', 100);
            $table->uuid('notable_id');
            $table->uuid('author_id');
            $table->uuid('parent_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('body');
            $table->string('kind', 30);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->string('visibility', 30);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX master_notes_organization_id_id_unique
            ON master_notes (organization_id, id)
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX master_notes_organization_id_index
            ON master_notes (organization_id)
            WHERE deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX master_notes_notable_index
            ON master_notes (organization_id, notable_type, notable_id)
            WHERE deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX master_notes_parent_id_index
            ON master_notes (parent_id, organization_id, position)
            WHERE deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX master_notes_listing_index
            ON master_notes (organization_id, pinned_at DESC, created_at DESC, id)
            WHERE deleted_at IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX master_notes_expiration_index
            ON master_notes (organization_id, expires_at)
            WHERE deleted_at IS NULL AND expires_at IS NOT NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX master_notes_thread_position_unique
            ON master_notes (organization_id, parent_id, position)
            WHERE deleted_at IS NULL
            SQL);

        Schema::table('master_notes', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'parent_id'], 'master_notes_organization_parent_foreign')
                ->references(['organization_id', 'id'])
                ->on('master_notes')
                ->restrictOnDelete();
        });

        Schema::create('master_note_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('note_id');
            $table->uuid('member_id');
            $table->timestamp('mentioned_at');
            $table->timestamp('read_at')->nullable();

            $table->foreign(['organization_id', 'note_id'], 'master_note_mentions_organization_note_foreign')
                ->references(['organization_id', 'id'])
                ->on('master_notes')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'member_id'], 'master_note_mentions_organization_member_foreign')
                ->references(['organization_id', 'id'])
                ->on('iam_organization_members')
                ->cascadeOnDelete();
            $table->unique(
                ['organization_id', 'note_id', 'member_id'],
                'master_note_mentions_organization_note_member_unique',
            );
            $table->index(['organization_id', 'note_id'], 'master_note_mentions_organization_note_index');
            $table->index(
                ['organization_id', 'member_id', 'read_at'],
                'master_note_mentions_organization_member_read_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_note_mentions');
        Schema::dropIfExists('master_notes');
    }
};
