<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 membuat `museum_staff.museum_id` tanpa foreign key karena tabel `museums`
 * belum ada. Migrasi ini melengkapi constraint tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('museum_staff', function (Blueprint $table): void {
            $table->foreign('museum_id')->references('id')->on('museums')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('museum_staff', function (Blueprint $table): void {
            $table->dropForeign(['museum_id']);
        });
    }
};
