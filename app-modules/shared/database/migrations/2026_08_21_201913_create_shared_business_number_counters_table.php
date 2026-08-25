<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_business_number_counters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->char('number_identity_hash', 64);
            $table->text('number_identity');
            $table->bigInteger('value');

            $table->unique(
                ['organization_id', 'number_identity_hash'],
                'shared_business_number_counters_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_business_number_counters');
    }
};
