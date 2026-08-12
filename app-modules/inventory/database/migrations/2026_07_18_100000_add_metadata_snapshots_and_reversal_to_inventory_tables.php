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
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->jsonb('stock_metadata_snapshot')->nullable()->after('metadata');
        });

        DB::statement(<<<'SQL'
            UPDATE inventory_movements AS movements
            SET stock_metadata_snapshot = stocks.metadata
            FROM inventory_stocks AS stocks
            WHERE movements.movement_type = 'out'
              AND movements.stock_id = stocks.id
              AND movements.stock_metadata_snapshot IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_in_snapshot_check
            CHECK (movement_type <> 'in' OR stock_metadata_snapshot IS NULL)
        SQL);

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignUuid('reversal_of_transaction_id')
                ->nullable()
                ->after('metadata')
                ->constrained('inventory_transactions')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX inventory_transactions_reversal_of_transaction_id_unique
            ON inventory_transactions (reversal_of_transaction_id)
            WHERE reversal_of_transaction_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_reversal_of_transaction_id_unique');
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT IF EXISTS inventory_movements_in_snapshot_check');

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_transaction_id']);
            $table->dropColumn('reversal_of_transaction_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('stock_metadata_snapshot');
        });
    }
};
