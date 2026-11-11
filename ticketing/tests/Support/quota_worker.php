<?php

/**
 * Pekerja konkurensi: menahan kuota dari proses terpisah.
 *
 * Dipakai oleh QuotaConcurrencyTest dan concurrency_probe. Setiap proses
 * membuka koneksi sendiri, sehingga pengujian benar-benar menguji serialisasi
 * di level database — bukan sekadar pemanggilan berurutan dalam satu proses.
 *
 * Argumen: [slot_id, quantity, barrier_file, result_file, config_file?]
 *
 * `config_file` berisi JSON konfigurasi koneksi. Ini penting karena proses
 * anak tidak mewarisi environment pengujian (mis. DB_DATABASE dari phpunit.xml),
 * sehingga tanpa konfigurasi eksplisit pekerja akan menyambung ke database lain.
 */

require __DIR__.'/../../vendor/autoload.php';

use App\Models\VisitSlot;
use App\Services\Quota\QuotaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $slotId, $quantity, $barrierFile, $resultFile] = $argv;

$configFile = $argv[5] ?? null;

if ($configFile !== null && is_file($configFile)) {
    $config = json_decode((string) file_get_contents($configFile), true);

    if (is_array($config)) {
        $name = 'concurrency';

        config([
            "database.connections.{$name}" => [
                'driver' => $config['driver'],
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
            'database.default' => $name,
        ]);

        DB::purge($name);
    }
}

$quantity = (int) $quantity;

// Tunggu sampai seluruh pekerja siap, lalu lepas bersamaan agar benar-benar
// memperebutkan baris yang sama pada waktu yang hampir identik.
$deadline = microtime(true) + 10;

while (! file_exists($barrierFile) && microtime(true) < $deadline) {
    usleep(2000);
}

try {
    $slot = VisitSlot::query()->findOrFail((int) $slotId);

    $ok = app(QuotaService::class)->reserve($slot, $quantity);

    file_put_contents($resultFile, $ok ? 'ok' : 'rejected');
} catch (Throwable $e) {
    file_put_contents($resultFile, 'error: '.$e->getMessage());
}
