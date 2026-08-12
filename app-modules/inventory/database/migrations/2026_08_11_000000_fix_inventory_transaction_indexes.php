<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER INDEX IF EXISTS inventory_transactions_organization_id_idempotency_key_unique RENAME TO inventory_transactions_org_idempotency_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_reversal_of_transaction_id_unique');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX inventory_transactions_org_reversal_unique
            ON inventory_transactions (organization_id, reversal_of_transaction_id)
            WHERE reversal_of_transaction_id IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX inventory_transactions_reversal_of_transaction_id_index
            ON inventory_transactions (reversal_of_transaction_id)
            WHERE reversal_of_transaction_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER INDEX IF EXISTS inventory_transactions_org_idempotency_unique RENAME TO inventory_transactions_organization_id_idempotency_key_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_reversal_of_transaction_id_index');
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_org_reversal_unique');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX inventory_transactions_reversal_of_transaction_id_unique
            ON inventory_transactions (reversal_of_transaction_id)
            WHERE reversal_of_transaction_id IS NOT NULL
        SQL);
    }
};
