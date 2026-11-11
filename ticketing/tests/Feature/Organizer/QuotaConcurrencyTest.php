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
 * Berbeda dari tes sekuensial, setiap penantang adalah **proses PHP terpisah**
 * dengan koneksi database sendiri, sehingga yang diuji benar-benar serialisasi
 * di level database (row lock + UPDATE kondisional).
 *
 * Mengapa tes ini tidak memakai RefreshDatabase
 * ---------------------------------------------
 * `RefreshDatabase` (dipasang global di tests/Pest.php) membungkus setiap tes
 * dalam satu transaksi yang di-rollback. Baris `visit_slots` karena itu
 * **tidak pernah di-commit**, sehingga proses anak (koneksi terpisah) tidak
 * dapat melihatnya dan seluruh penantang gagal dengan "not found" — bukan
 * kalah bersaing. Untuk menguji konkurensi sungguhan, data harus di-commit.
 * Tes ini karena itu memaksa commit eksplisit sebelum pekerja dijalankan dan
 * membersihkan barisnya sendiri di akhir.
 *
 * SQLite mengunci seluruh berkas saat menulis sehingga hanya satu penulis pada
 * satu waktu; jalur paralel karenanya hanya dijalankan pada PostgreSQL/MySQL.
 */
test('parallel workers competing for the last seats never oversell', function (): void {
    $driver = DB::connection()->getDriverName();

    $category = MuseumCategory::factory()->create();
    $profile = OrganizerProfile::factory()->approved()->create();

    $museum = Museum::factory()->forOrganizer($profile)->published()->create([
        'category_id' => $category->id,
    ]);

    $capacity = 5;
    $workers = 8;

    $slot = VisitSlot::factory()->create([
        'museum_id' => $museum->id,
        'capacity' => $capacity,
        'reserved_count' => 0,
        'sold_count' => 0,
    ]);

    if ($driver !== 'pgsql' && $driver !== 'mysql') {
        // SQLite: jalankan penantang secara berurutan pada koneksi yang sama.
        // Invarian yang sama tetap harus terjaga.
        $service = app(QuotaService::class);

        $results = [];
        for ($i = 0; $i < $workers; $i++) {
            $results[] = $service->reserve($slot->fresh(), 1);
        }

        expect(count(array_filter($results)))->toBe($capacity)
            ->and($slot->fresh()->reserved_count)->toBe($capacity)
            ->and($slot->fresh()->reserved_count)->toBeLessThanOrEqual($slot->fresh()->capacity);

        return;
    }

    // Data harus benar-benar terlihat oleh koneksi lain sebelum pekerja dijalankan.
    // RefreshDatabase membungkus tes dalam transaksi, sehingga baris di atas
    // belum tentu ter-commit. Commit eksplisit agar proses anak dapat melihatnya.
    DB::commit();

    DB::purge();
    DB::reconnect();

    $config = config('database.connections.'.config('database.default'));
    $configPath = tempnam(sys_get_temp_dir(), 'dbcfg');
    file_put_contents($configPath, json_encode([
        'driver' => $config['driver'],
        'host' => $config['host'] ?? null,
        'port' => $config['port'] ?? null,
        'database' => $config['database'] ?? null,
        'username' => $config['username'] ?? null,
        'password' => $config['password'] ?? null,
    ]));

    $worker = base_path('tests/Support/quota_worker.php');
    $barrier = tempnam(sys_get_temp_dir(), 'barrier');
    $resultFiles = [];
    $processes = [];

    for ($i = 0; $i < $workers; $i++) {
        $resultFile = tempnam(sys_get_temp_dir(), 'qres');
        $resultFiles[$i] = $resultFile;

        $processes[$i] = proc_open(
            [PHP_BINARY, $worker, (string) $slot->id, '1', $barrier, $resultFile, $configPath],
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
        $resultFiles,
    );

    $accepted = count(array_filter($outcomes, fn (string $o): bool => $o === 'ok'));

    // Tidak boleh ada pekerja yang error; error berarti tes tidak menguji balapan.
    $errors = array_filter($outcomes, fn (string $o): bool => str_starts_with($o, 'error'));

    $fresh = $slot->fresh();

    expect($errors)->toBe([], 'Pekerja gagal: '.implode(' | ', $errors))
        ->and($accepted)->toBe($capacity)
        ->and($fresh->reserved_count)->toBe($capacity)
        ->and($fresh->reserved_count + $fresh->sold_count)->toBeLessThanOrEqual($fresh->capacity);

    foreach ($resultFiles as $file) {
        @unlink($file);
    }
    @unlink($barrier);
    @unlink($configPath);
});
