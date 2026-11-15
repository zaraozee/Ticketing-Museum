<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Memeriksa kesiapan konfigurasi pembayaran Midtrans (AC-4 Fase 9).
 *
 * AC-4 menuntut satu pembayaran **produksi** bernilai kecil berhasil
 * end-to-end. Sebelum uang sungguhan dipakai, kesalahan konfigurasi harus
 * tertangkap lebih dulu — dan kesalahan di sini mahal karena gejalanya
 * menyesatkan:
 *
 * - kunci tertukar menghasilkan `401`, yang mudah disimpulkan sebagai
 *   "kunci salah" padahal yang salah adalah **kunci mana** yang dipakai;
 * - `MIDTRANS_IS_PRODUCTION=true` dengan kunci sandbox membuat Snap.js
 *   produksi menolak transaksi sandbox;
 * - `MIDTRANS_SNAP_URL` yang diisi manual dapat tertinggal di host sandbox
 *   meski mode produksi menyala.
 *
 * Perintah ini **tidak** membuat transaksi. Ia hanya membaca konfigurasi dan
 * memeriksa konsistensinya, sehingga aman dijalankan kapan saja — termasuk
 * sebelum akun produksi aktif.
 */
class CheckMidtransConfigCommand extends Command
{
    protected $signature = 'payments:check-midtrans {--probe : Kirim permintaan uji ke API Midtrans untuk memastikan kunci diterima}';

    protected $description = 'Periksa konfigurasi Midtrans (kunci, mode, URL) tanpa membuat transaksi';

    public function handle(): int
    {
        $serverKey = (string) config('services.midtrans.server_key');
        $clientKey = (string) config('services.midtrans.client_key');
        $signatureKey = (string) config('services.midtrans.signature_key');
        $isProduction = (bool) config('services.midtrans.is_production');
        $snapUrl = (string) config('services.midtrans.snap_url');
        $apiBase = (string) config('services.midtrans.api_base_url');
        $statusBase = (string) config('services.midtrans.status_api_base_url');

        $this->newLine();
        $this->line('  <options=bold>Konfigurasi Midtrans</>');
        $this->newLine();

        $problems = 0;

        $problems += $this->report(
            'MIDTRANS_SERVER_KEY',
            $serverKey !== '',
            $serverKey !== '' ? $this->mask($serverKey) : 'kosong',
            'Isi dengan Server Key (bukan Signature Secret Key). Hanya tampil sekali saat dibuat.',
        );

        $problems += $this->report(
            'MIDTRANS_CLIENT_KEY',
            $clientKey !== '',
            $clientKey !== '' ? $this->mask($clientKey) : 'kosong',
            'Isi dengan Client Key; dipakai Snap.js di browser.',
        );

        /*
         * Kesalahan paling mahal di proyek ini: Server Key diisi Signature
         * Secret Key. Keduanya berformat sama, jadi hanya perilaku API yang
         * dapat membedakan — karena itu kunci diperiksa silang dengan mode.
         */
        $problems += $this->checkKeyModeConsistency($serverKey, $clientKey, $isProduction);

        $this->report(
            'MIDTRANS_IS_PRODUCTION',
            true,
            $isProduction ? 'true (produksi)' : 'false (sandbox)',
            '',
        );

        $problems += $this->checkSignatureKey($signatureKey, $serverKey);

        $problems += $this->checkUrls($isProduction, $snapUrl, $apiBase, $statusBase);

        if (is_file(base_path('bootstrap/cache/config.php'))) {
            $this->newLine();
            $this->warn('  Konfigurasi sedang di-cache (bootstrap/cache/config.php).');
            $this->line('  Setelah mengubah .env, jalankan: <options=bold>php artisan config:clear</>');
        }

        $problems += $this->checkWebhookReachability($isProduction);

        if ($this->option('probe')) {
            $problems += $this->probeApi($serverKey, $statusBase);
        } else {
            $this->newLine();
            $this->line('  Untuk memastikan kunci benar-benar diterima Midtrans, jalankan:');
            $this->line('    <options=bold>php artisan payments:check-midtrans --probe</>');
        }

        $this->newLine();

        if ($problems === 0) {
            $this->info('  Konfigurasi konsisten.');

            if (! $isProduction) {
                $this->line('  Mode masih <options=bold>sandbox</>. Untuk AC-4, ulangi dengan kunci produksi:');
                $this->line('    1. MIDTRANS_IS_PRODUCTION=true');
                $this->line('    2. Ganti Server/Client Key dengan kunci produksi (tanpa prefix SB-)');
                $this->line('    3. Kosongkan MIDTRANS_SNAP_URL agar mengikuti mode');
                $this->line('    4. Daftarkan Payment Notification URL: https://<domain>/webhooks/midtrans');
                $this->line('    5. php artisan config:clear && php artisan config:cache');
            }

            $this->newLine();

            return self::SUCCESS;
        }

        $this->error("  {$problems} masalah ditemukan. Pembayaran belum dapat diandalkan.");
        $this->newLine();

        return self::FAILURE;
    }

    /**
     * Kunci sandbox berawalan `SB-Mid-`, kunci produksi tidak.
     *
     * Ketidakcocokan di sini tidak menghasilkan pesan yang jelas saat runtime:
     * Snap.js produksi menolak token sandbox dan sebaliknya.
     */
    private function checkKeyModeConsistency(string $serverKey, string $clientKey, bool $isProduction): int
    {
        $problems = 0;

        foreach (['MIDTRANS_SERVER_KEY' => $serverKey, 'MIDTRANS_CLIENT_KEY' => $clientKey] as $name => $value) {
            if ($value === '') {
                continue;
            }

            $looksSandbox = str_starts_with($value, 'SB-Mid-');

            if ($isProduction && $looksSandbox) {
                $this->line("  <fg=red>[X]</> {$name}: kunci sandbox pada mode produksi");
                $this->line('      Kunci berawalan SB-Mid- hanya berlaku di sandbox. Ambil kunci produksi dari dashboard Midtrans produksi.');
                $problems++;

                continue;
            }

            if (! $isProduction && ! $looksSandbox) {
                $this->line("  <fg=red>[X]</> {$name}: kunci produksi pada mode sandbox");
                $this->line('      Kunci produksi tidak dapat dipakai ke host sandbox. Set MIDTRANS_IS_PRODUCTION=true atau pakai kunci sandbox.');
                $problems++;
            }
        }

        return $problems;
    }

    /**
     * Signature Secret Key dipakai memverifikasi `signature_key` notifikasi.
     * Salah kunci di sini membuat **semua** notifikasi ditolak — pembayaran
     * lunas tidak pernah berubah menjadi tiket, dan gejalanya tampak seperti
     * "Midtrans tidak mengirim notifikasi".
     *
     * `config('services.midtrans.signature_key')` sengaja jatuh kembali ke
     * Server Key, jadi nilainya tidak pernah kosong; yang dapat diperiksa
     * adalah apakah ia sudah dipisahkan atau masih menumpang Server Key.
     */
    private function checkSignatureKey(string $signatureKey, string $serverKey): int
    {
        if ($signatureKey === '') {
            $this->line('  <fg=red>[X]</> MIDTRANS_SIGNATURE_KEY: tidak ada (Server Key juga kosong)');

            return 1;
        }

        if (hash_equals($signatureKey, $serverKey)) {
            $this->line('  <fg=green>[OK]</> MIDTRANS_SIGNATURE_KEY: memakai Server Key');
            $this->line('      Normal untuk akun yang belum meregenerasi Signature Secret Key.');

            return 0;
        }

        $this->line('  <fg=green>[OK]</> MIDTRANS_SIGNATURE_KEY: '.$this->mask($signatureKey).' (terpisah dari Server Key)');
        $this->line('      Kondisi normal setelah regenerasi. Notifikasi diverifikasi dengan kunci ini.');

        return 0;
    }

    /**
     * `snap_url` satu-satunya URL yang dapat melenceng: ia dapat diisi manual
     * lewat `MIDTRANS_SNAP_URL`, dan menyalakan mode produksi tidak mengubah
     * nilai yang sudah diisi. Dua URL lainnya diturunkan langsung dari
     * `MIDTRANS_IS_PRODUCTION`, jadi selalu ikut mode — keduanya hanya
     * dilaporkan, bukan diperiksa.
     */
    private function checkUrls(bool $isProduction, string $snapUrl, string $apiBase, string $statusBase): int
    {
        $expectedSnap = $isProduction
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';

        $actualSnap = rtrim($snapUrl, '/');

        $this->line('  <fg=green>[OK]</> API Snap: '.rtrim($apiBase, '/'));
        $this->line('  <fg=green>[OK]</> API Status: '.rtrim($statusBase, '/'));

        if ($actualSnap === $expectedSnap) {
            $this->line('  <fg=green>[OK]</> Snap.js: '.$actualSnap);

            return 0;
        }

        $this->line('  <fg=red>[X]</> Snap.js: '.$actualSnap);
        $this->line('      Mode '.($isProduction ? 'produksi' : 'sandbox')." seharusnya memakai {$expectedSnap}.");
        $this->line('      Kemungkinan MIDTRANS_SNAP_URL diisi manual. Kosongkan agar mengikuti mode.');

        return 1;
    }

    /**
     * Peringatkan bila URL notifikasi tidak dapat dijangkau Midtrans.
     *
     * Ini penyebab notifikasi hilang tanpa jejak: Midtrans mengirim ke alamat
     * yang tidak ada, dan aplikasi tidak pernah tahu ada percobaan pengiriman.
     */
    private function checkWebhookReachability(bool $isProduction): int
    {
        $appUrl = (string) config('app.url');

        $isLocal = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');

        if ($isLocal) {
            $this->newLine();

            if ($isProduction) {
                $this->line('  <fg=red>[X]</> APP_URL menunjuk ke localhost pada mode produksi: '.$appUrl);
                $this->line('      Midtrans produksi tidak dapat menjangkau webhook. Pembayaran akan lunas di Midtrans');
                $this->line('      tetapi tiket tidak pernah terbit — persis kegagalan yang membuat AC-4 menuntut');
                $this->line('      domain HTTPS tetap, bukan tunnel sementara.');
                $this->newLine();

                return 1;
            }

            $this->warn('  APP_URL menunjuk ke localhost — Midtrans tidak dapat menjangkau webhook.');
            $this->line('  Wajar untuk pengembangan: status hanya berubah lewat payments:reconcile.');
            $this->line('  Untuk AC-4, domain HTTPS tetap wajib.');
            $this->newLine();

            return 0;
        }

        if (! str_starts_with($appUrl, 'https://')) {
            $this->newLine();
            $this->line('  <fg=red>[X]</> APP_URL bukan HTTPS: '.$appUrl);
            $this->line('      Midtrans produksi mengirim notifikasi ke URL publik HTTPS.');
            $this->newLine();

            return 1;
        }

        $this->newLine();
        $this->line('  <fg=green>[OK]</> Webhook: '.rtrim($appUrl, '/').'/webhooks/midtrans');
        $this->line('      Daftarkan URL ini pada Settings > Payment > Payment Notification URL.');

        return 0;
    }

    /**
     * Kirim permintaan status untuk order yang tidak ada.
     *
     * `401` berarti kunci bukan Server Key yang sah; `404`/`200` dengan pesan
     * "Transaction doesn't exist" berarti kunci **diterima** — inilah yang
     * ingin diketahui sebelum memakai uang sungguhan.
     */
    private function probeApi(string $serverKey, string $statusBase): int
    {
        if ($serverKey === '') {
            return 0;
        }

        $this->newLine();
        $this->line('  Mengirim permintaan uji ke Midtrans...');

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->timeout(15)
                ->acceptJson()
                ->get(rtrim($statusBase, '/').'/v2/musentix-config-probe/status');
        } catch (Throwable $exception) {
            $this->error('  Gagal menghubungi Midtrans: '.$exception->getMessage());

            return 1;
        }

        $status = $response->status();
        $body = $response->json();
        $message = is_array($body) ? (string) ($body['status_message'] ?? '') : '';

        if ($status === 401) {
            $this->error('  [X] HTTP 401 — kunci ditolak. Kemungkinan MIDTRANS_SERVER_KEY berisi Signature Secret Key, atau kunci sudah di-revoke.');

            return 1;
        }

        if ($status === 403) {
            $this->error('  [X] HTTP 403 — kunci diterima tetapi tidak punya izin. Beri izin Core API Read pada Server Key.');

            return 1;
        }

        // Kunci diterima: Midtrans menjawab "tidak ada transaksi", bukan menolak.
        $this->line("  <fg=green>[OK]</> Kunci diterima Midtrans (HTTP {$status}"
            .($message !== '' ? ": {$message}" : '')
            .').');

        return 0;
    }

    private function report(string $name, bool $ok, string $value, string $hint): int
    {
        if ($ok) {
            $this->line("  <fg=green>[OK]</> {$name}: {$value}");

            return 0;
        }

        $this->line("  <fg=red>[X]</> {$name}: {$value}");

        if ($hint !== '') {
            $this->line("      {$hint}");
        }

        return 1;
    }

    /**
     * Tampilkan sebagian nilai agar dapat dicocokkan dengan dashboard tanpa
     * mencetak rahasia penuh ke terminal atau log.
     */
    private function mask(string $value): string
    {
        if (mb_strlen($value) <= 12) {
            return str_repeat('*', mb_strlen($value));
        }

        return mb_substr($value, 0, 8).'...'.mb_substr($value, -4);
    }
}
