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
        Schema::table('organization_organizations', function (Blueprint $table): void {
            $table->string('functional_currency_code', 3)
                ->nullable()
                ->after('name');
        });

        DB::table('organization_organizations')
            ->whereNull('functional_currency_code')
            ->update(['functional_currency_code' => 'XOF']);

        Schema::table('organization_organizations', function (Blueprint $table): void {
            $table->string('functional_currency_code', 3)
                ->nullable(false)
                ->change();
            $table->foreign('functional_currency_code')
                ->references('code')
                ->on('master_currencies')
                ->restrictOnDelete();
        });

        DB::statement('CREATE INDEX organization_organizations_functional_currency_code_index ON organization_organizations (functional_currency_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('organization_organizations', function (Blueprint $table): void {
            $table->dropForeign(['functional_currency_code']);
            $table->dropColumn('functional_currency_code');
        });

        DB::statement('DROP INDEX IF EXISTS organization_organizations_functional_currency_code_index');
    }
};
