<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksaan kesehatan operasional (NFR-13, AC-5 Fase 9).
 *
 * Dirancang untuk dipanggil monitor eksternal (cron + uptime monitor): keluar
 * dengan kode ≠ 0 bila ada kondisi yang butuh perhatian, sehingga alarm dapat
 * dipasang tanpa perlu membaca output.
 *
 * Ambang batas diambil dari `config('ticketing.monitoring')` agar dapat
 * disetel per lingkungan.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'ops:health {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Periksa antrean, webhook, dan pembayaran menggantung; keluar ≠ 0 bila ada masalah';

    public function handle(): int
    {
        $checks = [
            $this->failedJobs(),
            $this->staleQueue(),
            $this->stuckPayments(),
            $this->pendingWebhookEvents(),
        ];

        $failing = array_values(array_filter($checks, fn (array $check): bool => $check['status'] === 'fail'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $failing === [],
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check) {
                $this->line(sprintf(
                    '[%s] %s: %s',
                    strtoupper($check['status']),
                    $check['name'],
                    $check['message'],
                ));
            }
        }

        $this->pingMonitor($checks, $failing);

        return $failing === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Beri tahu Healthchecks.io hasil pemeriksaan (AC-5 Fase 9).
     *
     * Memakai `/fail` alih-alih berhenti mengirim ping supaya alarm langsung
     * berbunyi saat ada masalah, tanpa menunggu grace time habis. Kegagalan
     * mengirim ping tidak boleh mengubah hasil pemeriksaan — monitor yang mati
     * adalah masalah terpisah dari kesehatan aplikasi.
     *
     * @param  list<array{name: string, status: string, message: string}>  $checks
     * @param  list<array{name: string, status: string, message: string}>  $failing
     */
    private function pingMonitor(array $checks, array $failing): void
    {
        $pingUrl = (string) config('ticketing.monitoring.ping_url', '');

        if ($pingUrl === '') {
            return;
        }

        $url = $failing === [] ? $pingUrl : rtrim($pingUrl, '/').'/fail';

        // Ringkasan disertakan pada body agar terbaca di halaman detail check.
        $body = collect($checks)
            ->map(fn (array $check): string => sprintf('[%s] %s: %s', $check['status'], $check['name'], $check['message']))
            ->implode("\n");

        try {
            Http::timeout(10)->withBody($body, 'text/plain')->post($url);
        } catch (ConnectionException $e) {
            Log::warning('ops.health_ping_failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Job gagal menumpuk menandakan bug atau ketergantungan eksternal mati.
     *
     * @return array{name: string, status: string, message: string}
     */
    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return $this->check('Antrean gagal', 'ok', 'Tabel failed_jobs tidak ada.');
        }

        $count = DB::table('failed_jobs')->count();
        $threshold = (int) config('ticketing.monitoring.failed_jobs_threshold');

        return $count > $threshold
            ? $this->check('Antrean gagal', 'fail', "{$count} job gagal (ambang {$threshold}).")
            : $this->check('Antrean gagal', 'ok', "{$count} job gagal.");
    }

    /**
     * Job yang menunggu terlalu lama berarti worker tidak berjalan.
     *
     * @return array{name: string, status: string, message: string}
     */
    private function staleQueue(): array
    {
        if (! Schema::hasTable('jobs')) {
            return $this->check('Antrean menunggu', 'ok', 'Tabel jobs tidak ada.');
        }

        $minutes = (int) config('ticketing.monitoring.queue_age_minutes');

        $oldest = DB::table('jobs')->min('created_at');

        if ($oldest === null) {
            return $this->check('Antrean menunggu', 'ok', 'Antrean kosong.');
        }

        $ageMinutes = (int) floor((time() - (int) $oldest) / 60);

        return $ageMinutes > $minutes
            ? $this->check('Antrean menunggu', 'fail', "Job tertua berumur {$ageMinutes} menit (ambang {$minutes}).")
            : $this->check('Antrean menunggu', 'ok', "Job tertua berumur {$ageMinutes} menit.");
    }

    /**
     * Pembayaran `pending` yang jauh melewati batas waktunya menandakan
     * webhook dan rekonsiliasi sama-sama gagal (BR-12).
     *
     * @return array{name: string, status: string, message: string}
     */
    private function stuckPayments(): array
    {
        $minutes = (int) config('ticketing.monitoring.stuck_payment_minutes');

        $count = Payment::query()
            ->where('status', PaymentStatus::PENDING->value)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->count();

        return $count > 0
            ? $this->check('Pembayaran menggantung', 'fail', "{$count} pembayaran pending lebih dari {$minutes} menit.")
            : $this->check('Pembayaran menggantung', 'ok', 'Tidak ada pembayaran menggantung.');
    }

    /**
     * Notifikasi dengan signature sah tetapi belum diproses berarti worker
     * webhook tertahan.
     *
     * @return array{name: string, status: string, message: string}
     */
    private function pendingWebhookEvents(): array
    {
        if (! Schema::hasTable('payment_events')) {
            return $this->check('Webhook tertunda', 'ok', 'Tabel payment_events tidak ada.');
        }

        $minutes = (int) config('ticketing.monitoring.webhook_lag_minutes');

        $count = DB::table('payment_events')
            ->where('signature_valid', true)
            ->whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->count();

        return $count > 0
            ? $this->check('Webhook tertunda', 'fail', "{$count} notifikasi sah belum diproses lebih dari {$minutes} menit.")
            : $this->check('Webhook tertunda', 'ok', 'Tidak ada notifikasi tertunda.');
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function check(string $name, string $status, string $message): array
    {
        return ['name' => $name, 'status' => $status, 'message' => $message];
    }
}
