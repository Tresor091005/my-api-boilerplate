<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iam_external_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('iam_users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('issuer');
            $table->string('subject');
            $table->timestamps();
            $table->unique(['provider', 'issuer', 'subject']);
            $table->unique(['user_id', 'provider']);
        });
        Schema::create('iam_google_auth_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nonce_hash', 64);
            $table->jsonb('identity')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void {}
};
