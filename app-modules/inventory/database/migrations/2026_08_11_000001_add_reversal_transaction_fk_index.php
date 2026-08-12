<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS inventory_transactions_reversal_of_transaction_id_index
            ON inventory_transactions (reversal_of_transaction_id)
            WHERE reversal_of_transaction_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_reversal_of_transaction_id_index');
    }
};
