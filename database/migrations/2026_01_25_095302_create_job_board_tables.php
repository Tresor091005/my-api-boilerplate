<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->index()->constrained('users')->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->text('cv_path')->nullable();
            $table->text('linkedin_url')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('website')->nullable();
            $table->text('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('company_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->index()->constrained('companies')->onDelete('cascade');
            $table->text('first_name');
            $table->text('last_name');
            $table->text('email')->unique();
            $table->text('password');
            $table->timestamps();
        });

        Schema::create('career_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->nullable()->index()->constrained('companies')->onDelete('cascade');
            $table->foreignUuid('posted_by')->nullable()->index()->constrained('company_members')->onDelete('set null');
            $table->text('title');
            $table->text('description');
            $table->text('location')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->decimal('salary')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('career_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('career_job_id')->index()->constrained('career_jobs')->onDelete('cascade');
            $table->foreignUuid('user_id')->index()->constrained('users')->onDelete('cascade');
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['pending', 'viewed', 'rejected', 'accepted'])->default('pending');
            $table->timestamps();

            $table->unique(['career_job_id', 'user_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('type')->index();
            $table->text('value');
            $table->text('slug')->unique();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tag_id')->constrained('tags')->onDelete('cascade');
            $table->uuidMorphs('taggable');
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('career_applications');
        Schema::dropIfExists('career_jobs');
        Schema::dropIfExists('company_members');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('user_profiles');
    }
};
