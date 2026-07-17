<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable();
            $table->char('payload_hash', 64)->nullable();
        });

        DB::table('inventory_transactions')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $transaction): void {
                $legacyKey = 'legacy:'.$transaction->id;

                DB::table('inventory_transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'idempotency_key' => $legacyKey,
                        'payload_hash'    => hash('sha256', $legacyKey),
                    ]);
            });

        DB::statement('ALTER TABLE inventory_transactions ALTER COLUMN idempotency_key SET NOT NULL');
        DB::statement('ALTER TABLE inventory_transactions ALTER COLUMN payload_hash SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_transactions_organization_id_idempotency_key_unique
            ON inventory_transactions (organization_id, idempotency_key)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_transactions_organization_id_idempotency_key_unique');

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropColumn(['idempotency_key', 'payload_hash']);
        });
    }
};
