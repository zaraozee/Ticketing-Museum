<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 — jenis tiket, jadwal, dan kuota.
 *
 * Mengikuti docs/ERD.md §3.3 dengan keputusan D-26..D-28:
 * - tabel kuota bernama `visit_slots`, kolom `reserved_count` (bukan `visit_sessions`/`held_count`)
 * - `slot_templates` berdiri sendiri sebagai cetakan slot
 * - override kuota per tanggal disimpan di `slot_capacity_overrides` agar tidak
 *   ditimpa oleh `slots:generate`
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // Harga disimpan sebagai integer rupiah (tanpa desimal) agar tidak ada
            // pembulatan floating point pada perhitungan pembayaran.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            // Nama jenis tiket unik per museum selama belum dihapus (ERD §4).
            $table->unique(['museum_id', 'name']);
            $table->index(['museum_id', 'is_active', 'sort_order']);
        });

        Schema::create('slot_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->string('name', 100);
            // Null untuk museum `booking_mode = daily` (satu slot = seluruh hari).
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['museum_id', 'is_active', 'sort_order']);
        });

        Schema::create('visit_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->restrictOnDelete();
            $table->date('visit_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('reserved_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['museum_id', 'visit_date']);
            $table->index(['museum_id', 'visit_date', 'status']);
        });

        Schema::create('slot_capacity_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->cascadeOnDelete();
            $table->date('visit_date');
            // Null berarti berlaku untuk mode `daily` (satu kuota per hari).
            $table->time('start_time')->nullable();
            $table->unsignedInteger('capacity');
            $table->string('note', 190)->nullable();
            $table->timestamps();

            $table->index(['museum_id', 'visit_date']);
        });

        $this->addUniqueConstraints();
        $this->addCheckConstraints();
    }

    /**
     * Unique index yang melibatkan kolom nullable.
     *
     * `start_time` bernilai NULL untuk museum `booking_mode = daily`. PostgreSQL
     * menganggap setiap NULL berbeda, sehingga UQ biasa tidak akan mencegah
     * baris ganda. Karena itu dipakai unique index ekspresi dengan COALESCE.
     */
    private function addUniqueConstraints(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX visit_slots_unique_slot ON visit_slots (museum_id, visit_date, COALESCE(start_time, \'00:00:00\'))');
            DB::statement('CREATE UNIQUE INDEX slot_capacity_overrides_unique ON slot_capacity_overrides (museum_id, visit_date, COALESCE(start_time, \'00:00:00\'))');
            DB::statement('CREATE UNIQUE INDEX slot_templates_unique_start ON slot_templates (museum_id, COALESCE(start_time, \'00:00:00\')) WHERE deleted_at IS NULL');

            return;
        }

        if ($driver === 'mysql') {
            // MySQL 8 mendukung functional index; nilai NULL tetap dibedakan,
            // sehingga ekspresi COALESCE dipakai dengan cara yang sama.
            DB::statement('CREATE UNIQUE INDEX visit_slots_unique_slot ON visit_slots (museum_id, visit_date, (COALESCE(start_time, \'00:00:00\')))');
            DB::statement('CREATE UNIQUE INDEX slot_capacity_overrides_unique ON slot_capacity_overrides (museum_id, visit_date, (COALESCE(start_time, \'00:00:00\')))');
            DB::statement('CREATE UNIQUE INDEX slot_templates_unique_start ON slot_templates (museum_id, (COALESCE(start_time, \'00:00:00\')))');

            return;
        }

        // SQLite (dipakai pada pengujian lokal) mendukung unique index ekspresi.
        DB::statement('CREATE UNIQUE INDEX visit_slots_unique_slot ON visit_slots (museum_id, visit_date, COALESCE(start_time, \'00:00:00\'))');
        DB::statement('CREATE UNIQUE INDEX slot_capacity_overrides_unique ON slot_capacity_overrides (museum_id, visit_date, COALESCE(start_time, \'00:00:00\'))');
        DB::statement('CREATE UNIQUE INDEX slot_templates_unique_start ON slot_templates (museum_id, COALESCE(start_time, \'00:00:00\')) WHERE deleted_at IS NULL');
    }

    /**
     * CHECK constraint sebagai jaring pengaman terakhir (BR-05, BR-20, ERD §3.3).
     *
     * Aturan terpenting: `reserved_count + sold_count <= capacity`. Walaupun
     * aplikasi sudah memakai update kondisional, constraint ini memastikan
     * overbooking tidak mungkin terjadi karena bug di lapisan aplikasi.
     */
    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            return;
        }

        DB::statement('ALTER TABLE visit_slots ADD CONSTRAINT visit_slots_no_overbooking_check CHECK (reserved_count + sold_count <= capacity)');
        DB::statement('ALTER TABLE visit_slots ADD CONSTRAINT visit_slots_reserved_check CHECK (reserved_count >= 0)');
        DB::statement('ALTER TABLE visit_slots ADD CONSTRAINT visit_slots_sold_check CHECK (sold_count >= 0)');
        DB::statement("ALTER TABLE visit_slots ADD CONSTRAINT visit_slots_status_check CHECK (status IN ('open','closed','cancelled'))");
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_age_check CHECK (min_age IS NULL OR max_age IS NULL OR min_age <= max_age)');
        DB::statement('ALTER TABLE slot_templates ADD CONSTRAINT slot_templates_time_check CHECK (start_time IS NULL OR end_time IS NULL OR start_time < end_time)');
        DB::statement('ALTER TABLE visit_slots ADD CONSTRAINT visit_slots_time_check CHECK (start_time IS NULL OR end_time IS NULL OR start_time < end_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_capacity_overrides');
        Schema::dropIfExists('visit_slots');
        Schema::dropIfExists('slot_templates');
        Schema::dropIfExists('ticket_types');
    }
};
