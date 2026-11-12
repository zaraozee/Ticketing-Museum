<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — booking, pembayaran, dan penerbitan tiket.
 *
 * Mengikuti docs/ERD.md §3.4–3.5 dengan keputusan:
 * - D-29: tabel `tickets` dibuat di fase ini karena BR-07 mewajibkan tiket
 *   terbit dalam transaksi konfirmasi.
 * - D-30: `tickets.status` = active/used/void/expired (§11.2), bukan `issued`.
 * - D-33: semua nominal uang adalah integer rupiah, tidak pernah float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Kode pendek yang mudah dibaca pengguna dan dibacakan ke petugas.
            $table->string('booking_code', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('museum_id')->constrained('museums')->restrictOnDelete();
            $table->foreignId('visit_slot_id')->constrained('visit_slots')->restrictOnDelete();
            $table->string('status', 20)->default('pending_payment');
            // Total dalam rupiah bulat; harus sama dengan jumlah subtotal item.
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('contact_name', 120);
            $table->string('contact_email', 190);
            $table->string('contact_phone', 30);
            // Snapshot agar riwayat tetap terbaca walau museum berubah (BR-19).
            $table->string('museum_name_snapshot', 160);
            $table->date('visit_date_snapshot');
            $table->string('slot_label_snapshot', 60);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['museum_id', 'status']);
            // Dipakai job `bookings:expire` untuk mencari booking yang lewat batas.
            $table->index(['status', 'expires_at']);
            $table->index('visit_slot_id');
        });

        Schema::create('booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // FK tetap disimpan agar laporan per jenis tiket dapat dibuat,
            // sementara snapshot menjaga nilai historis (ERD §4).
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->string('ticket_name_snapshot', 100);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();

            $table->index('booking_id');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('provider', 20)->default('midtrans');
            // `{booking_code}-{attempt}`; unik agar percobaan tidak tertukar.
            $table->string('provider_order_id', 64)->unique();
            $table->string('provider_transaction_id', 100)->nullable();
            $table->string('payment_type', 40)->nullable();
            $table->unsignedBigInteger('gross_amount');
            $table->string('status', 20)->default('created');
            $table->string('snap_token', 190)->nullable();
            $table->string('snap_redirect_url', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            // FR-PAY-07: antrean anomali yang harus diputuskan Admin.
            $table->boolean('needs_review')->default(false);
            $table->string('review_note', 190)->nullable();
            // Ringkasan respons gateway; tidak boleh memuat data kartu.
            $table->json('last_response')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            // Dipakai scheduler untuk memeriksa payment yang menggantung.
            $table->index(['status', 'expires_at']);
            $table->index('needs_review');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            // Null bila order_id tidak dikenal (payload asing).
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 20)->default('midtrans');
            // Kunci idempotensi: hash dari isi notifikasi (ERD §3.4).
            $table->string('event_key', 64)->unique();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('process_result', 30);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['process_result', 'created_at']);
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Kode pendek untuk dibacakan ke petugas; tanpa karakter ambigu.
            $table->string('ticket_code', 16)->unique();
            // sha256(token); token asli tidak disimpan (ERD §5).
            $table->char('token_hash', 64)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('booking_item_id')->constrained('booking_items')->restrictOnDelete();
            // Urutan tiket dalam satu item; mencegah tiket ganda saat konfirmasi diulang.
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('museum_id')->constrained('museums')->restrictOnDelete();
            $table->foreignId('visit_slot_id')->constrained('visit_slots')->restrictOnDelete();
            $table->string('holder_name', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Kunci anti-duplikat: satu urutan hanya boleh terbit sekali.
            $table->unique(['booking_item_id', 'sequence']);
            $table->index(['museum_id', 'visit_slot_id', 'status']);
            $table->index('booking_id');
        });

        $this->addCheckConstraints();
    }

    /**
     * CHECK sebagai jaring pengaman terakhir (SYSTEM_DESIGN §11.2).
     */
    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            return;
        }

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending_payment','confirmed','expired','cancelled','refunded'))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_total_check CHECK (total_amount >= 0)');

        DB::statement('ALTER TABLE booking_items ADD CONSTRAINT booking_items_quantity_check CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE booking_items ADD CONSTRAINT booking_items_amount_check CHECK (unit_price >= 0 AND subtotal >= 0)');

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('created','pending','paid','failed','expired','cancelled','refunded'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (gross_amount >= 0)');

        DB::statement("ALTER TABLE payment_events ADD CONSTRAINT payment_events_result_check CHECK (process_result IN ('applied','ignored_duplicate','rejected_signature','amount_mismatch','unknown_order','needs_review'))");

        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('active','used','void','expired'))");
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_sequence_check CHECK (sequence >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
