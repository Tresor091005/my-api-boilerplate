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
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->unique()
                ->constrained('organization_organizations')
                ->cascadeOnDelete();
            $table->jsonb('enable_currencies');
            $table->timestamps();
        });

        DB::table('organization_organizations')
            ->select(['id', 'functional_currency_code'])
            ->get()
            ->each(function (object $organization): void {
                DB::table('organization_settings')->insert([
                    'id'                => (string) str()->uuid7(),
                    'organization_id'   => $organization->id,
                    'enable_currencies' => json_encode([$organization->functional_currency_code], JSON_THROW_ON_ERROR),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
