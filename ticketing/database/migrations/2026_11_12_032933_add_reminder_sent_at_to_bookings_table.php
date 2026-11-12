<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda pengingat H-1 (FR-NTF-03).
 *
 * Disimpan di `bookings`, bukan di tabel terpisah, karena sifatnya satu nilai
 * per booking. Kolom nullable: booking lama tidak dianggap sudah diingatkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('expired_at');

            // Command pengingat menyaring booking yang belum diingatkan pada
            // tanggal kunjungan tertentu; indeks ini menjaga query tetap murah
            // saat jumlah booking bertambah.
            $table->index(['visit_date_snapshot', 'reminder_sent_at'], 'bookings_reminder_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_reminder_lookup_index');
            $table->dropColumn('reminder_sent_at');
        });
    }
};
