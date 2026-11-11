<?php

use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\OrganizerProfile;
use App\Models\VisitSlot;
use App\Services\Quota\QuotaService;
use Illuminate\Support\Facades\DB;

/**
 * AC-4 Fase 4: N proses paralel memperebutkan kuota terakhir.
 *
 * Berbeda dari tes sekuensial, di sini setiap penantang adalah **proses PHP
 * terpisah** dengan koneksi database sendiri, sehingga yang diuji benar-benar
 * serialisasi di level database (row lock + UPDATE kondisional).
 *
 * Catatan: SQLite mengunci seluruh berkas saat menulis, sehingga konkurensi
 * sesungguhnya hanya dapat diuji pada PostgreSQL/MySQL. Pada SQLite tes ini
 * memverifikasi invarian akhir (total reserved <= capacity) dan melewati
 * bagian paralel.
 */
test('parallel workers competing for the last seats never oversell', function (): void {
    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $museum = Museum::factory()->forOrganizer($profile)->published()->create([
        'category_id' => $category->id,
    ]);

    $slot = VisitSlot::factory()->create([
        'museum_id' => $museum->id,
        'capacity' => 5,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);

    $driver = DB::connection()->getDriverName();

    if ($driver !== 'pgsql' && $driver !== 'mysql') {
        // SQLite: jalankan penantang secara berurutan pada koneksi yang sama.
        // Invarian yang sama tetap harus terjaga.
        $service = app(QuotaService::class);

        $results = [];
        for ($i = 0; $i < 8; $i++) {
            $results[] = $service->reserve($slot->fresh(), 1);
        }

        expect(count(array_filter($results)))->toBe(5)
            ->and($slot->fresh()->reserved_count)->toBe(5)
            ->and($slot->fresh()->reserved_count)->toBeLessThanOrEqual($slot->fresh()->capacity);

        return;
    }

    $worker = base_path('tests/Support/quota_worker.php');
    $barrier = tempnam(sys_get_temp_dir(), 'barrier');
    $results = [];

    $processes = [];

    for ($i = 0; $i < 8; $i++) {
        $resultFile = tempnam(sys_get_temp_dir(), 'qres');
        $results[$i] = $resultFile;

        $processes[$i] = proc_open(
            [PHP_BINARY, $worker, (string) $slot->id, '1', $barrier, $resultFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
    }

    // Lepas barrier: seluruh pekerja mulai hampir bersamaan.
    file_put_contents($barrier, 'go');

    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    $outcomes = array_map(
        fn (string $file): string => trim((string) file_get_contents($file)),
        $results,
    );

    $accepted = count(array_filter($outcomes, fn (string $o): bool => $o === 'ok'));

    $fresh = $slot->fresh();

    expect($accepted)->toBe(5)
        ->and($fresh->reserved_count)->toBe(5)
        ->and($fresh->reserved_count)->toBeLessThanOrEqual($fresh->capacity);

    foreach ($results as $file) {
        @unlink($file);
    }
    @unlink($barrier);
});
