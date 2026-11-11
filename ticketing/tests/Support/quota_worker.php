<?php

/**
 * Pekerja konkurensi: menahan kuota dari proses terpisah.
 *
 * Dipakai oleh QuotaConcurrencyTest. Setiap proses membuka koneksi sendiri,
 * sehingga pengujian benar-benar menguji serialisasi di level database —
 * bukan sekadar pemanggilan berurutan dalam satu proses.
 *
 * Argumen: [slot_id, quantity, barrier_file, result_file]
 */

require __DIR__.'/../../vendor/autoload.php';

use App\Models\VisitSlot;
use App\Services\Quota\QuotaService;
use Illuminate\Contracts\Console\Kernel;

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $slotId, $quantity, $barrierFile, $resultFile] = $argv;

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
