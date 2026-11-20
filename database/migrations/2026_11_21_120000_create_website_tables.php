<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public website CMS (FINAL_SPECIFICATION §5): pages, news & events, FAQs,
 * amenities, redirects and website enquiries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('nav_label', 64)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24);
            $table->string('status', 24);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->timestampTz('event_starts_at')->nullable();
            $table->timestampTz('event_ends_at')->nullable();
            $table->string('event_location')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
        });

        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_published_has_date CHECK (status <> 'published' OR published_at IS NOT NULL)");
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_event_dates_valid CHECK (event_ends_at IS NULL OR event_starts_at IS NULL OR event_ends_at >= event_starts_at)');

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 64)->nullable();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 24);
            $table->string('icon', 64)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });

        Schema::create('accommodation_type_amenity', function (Blueprint $table): void {
            $table->foreignId('accommodation_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['accommodation_type_id', 'amenity_id']);
        });

        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestampTz('last_hit_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE redirects ADD CONSTRAINT redirects_status_valid CHECK (status_code IN (301, 302))');

        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24);
            $table->string('status', 24)->default('new');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('message')->nullable();
            $table->foreignId('accommodation_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->nullable();
            $table->bigInteger('quoted_total_cents')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement('ALTER TABLE enquiries ADD CONSTRAINT enquiries_has_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)');
        DB::statement('ALTER TABLE enquiries ADD CONSTRAINT enquiries_dates_valid CHECK (departure_date IS NULL OR arrival_date IS NULL OR departure_date > arrival_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('accommodation_type_amenity');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('pages');
    }
};
