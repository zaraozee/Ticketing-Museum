<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — modul museum dan pengelolaan konten.
 *
 * Mengikuti docs/ERD.md §3.2 dengan keputusan D-22..D-25:
 * - status museum: draft/pending_review/published/rejected/suspended/archived
 * - fasilitas: master `facilities` + pivot `museum_facility`
 * - video: `youtube_video_id` (char 11)
 * - kolom mengikuti BR-03/BR-15 (max_tickets_per_booking, entry_early_minutes, entry_late_minutes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('museum_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('museum_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('museums', function (Blueprint $table): void {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->foreignId('organizer_profile_id')->constrained('organizer_profiles')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('museum_categories')->restrictOnDelete();
            $table->string('name', 190);
            $table->string('slug', 200)->unique();
            $table->string('venue_type', 30)->default('museum');
            $table->text('description')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 40)->default('Asia/Jakarta');
            $table->string('status', 30)->default('draft');
            $table->string('booking_mode', 20)->default('daily');
            $table->unsignedSmallInteger('max_tickets_per_booking')->default(10);
            $table->unsignedSmallInteger('entry_early_minutes')->default(15);
            $table->unsignedSmallInteger('entry_late_minutes')->default(30);
            $table->char('youtube_video_id', 11)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_note', 500)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'city']);
            $table->index(['category_id', 'status']);
            $table->index('organizer_profile_id');
        });

        Schema::create('museum_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('caption', 190)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['museum_id', 'sort_order']);
        });

        Schema::create('museum_facility', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['museum_id', 'facility_id']);
        });

        Schema::create('museum_operating_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['museum_id', 'day_of_week']);
        });

        Schema::create('museum_closed_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->date('closed_on');
            $table->string('reason', 190)->nullable();
            $table->timestamps();

            $table->unique(['museum_id', 'closed_on']);
        });

        Schema::create('content_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 190);
            $table->string('slug', 200)->unique();
            $table->string('type', 20)->default('page');
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_published', 'sort_order']);
        });

        $this->addCheckConstraints();
    }

    /**
     * CHECK constraint sebagai jaring pengaman data (ERD §3.2).
     * Dipisah agar mudah dilewati pada driver yang tidak mendukung.
     */
    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            return;
        }

        $statuses = "'draft','pending_review','published','rejected','suspended','archived'";
        $modes = "'daily','timed_slot'";

        DB::statement("ALTER TABLE museums ADD CONSTRAINT museums_status_check CHECK (status IN ({$statuses}))");
        DB::statement("ALTER TABLE museums ADD CONSTRAINT museums_booking_mode_check CHECK (booking_mode IN ({$modes}))");
        DB::statement('ALTER TABLE museums ADD CONSTRAINT museums_latitude_check CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90))');
        DB::statement('ALTER TABLE museums ADD CONSTRAINT museums_longitude_check CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180))');
        DB::statement('ALTER TABLE museums ADD CONSTRAINT museums_quota_limit_check CHECK (max_tickets_per_booking >= 1 AND max_tickets_per_booking <= 50)');
        DB::statement('ALTER TABLE museum_operating_hours ADD CONSTRAINT museum_operating_hours_day_check CHECK (day_of_week BETWEEN 0 AND 6)');
        DB::statement("ALTER TABLE content_pages ADD CONSTRAINT content_pages_type_check CHECK (type IN ('page','faq'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pages');
        Schema::dropIfExists('museum_closed_dates');
        Schema::dropIfExists('museum_operating_hours');
        Schema::dropIfExists('museum_facility');
        Schema::dropIfExists('museum_images');
        Schema::dropIfExists('museums');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('museum_categories');
    }
};
