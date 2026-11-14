<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\MidtransClient;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Command;

/**
 * Cadangan bila webhook hilang (THIRD_PARTY_INTEGRATIONS §1.3 langkah 8).
 *
 * Memeriksa payment `pending` yang lebih tua dari N menit lewat API status,
 * sehingga pembayaran yang berhasil tetap dikonfirmasi walau notifikasi tidak
 * pernah sampai.
 */
class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
        {--minutes=10 : Hanya payment yang lebih tua dari sekian menit}
        {--grace-hours=2 : Periksa juga payment yang ditutup karena booking berakhir, selama sekian jam}
        {--limit=100 : Jumlah maksimum payment yang diperiksa}';

    protected $description = 'Periksa ulang status payment menggantung lewat API gateway';

    public function handle(MidtransClient $client, PaymentService $payments): int
    {
        if (! $client->isLive()) {
            $this->warn('Gateway tidak terkonfigurasi (MIDTRANS_SERVER_KEY kosong); rekonsiliasi dilewati.');

            return self::SUCCESS;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $graceHours = max(0, (int) $this->option('grace-hours'));
        $limit = max(1, (int) $this->option('limit'));

        $candidates = Payment::query()
            ->where('needs_review', false)
            ->where(function ($query) use ($minutes, $graceHours): void {
                // Belum lunas dan sudah lewat tenggang: notifikasi mungkin hilang.
                $query->where(function ($active) use ($minutes): void {
                    $active->whereIn('status', [PaymentStatus::CREATED->value, PaymentStatus::PENDING->value])
                        ->where('created_at', '<=', now()->subMinutes($minutes));
                });

                if ($graceHours === 0) {
                    return;
                }

                // Payment yang ditutup karena booking-nya berakhir tetap diperiksa
                // selama masa tenggang. Tanpa ini, pembayaran telat (BR-23) yang
                // notifikasinya juga hilang tidak akan pernah dipulihkan: uangnya
                // masuk tetapi tidak ada tiket dan tidak ada yang menanganinya.
                //
                // Jendela dihitung dari `expired_at`/`cancelled_at` milik booking,
                // **bukan** dari `payments.updated_at`: setiap pemeriksaan menulis
                // `last_response` sehingga `updated_at` ikut bergeser, dan payment
                // akan terus dipanggil ke gateway tanpa batas.
                $query->orWhere(function ($closed) use ($graceHours): void {
                    $closed->whereIn('status', [PaymentStatus::EXPIRED->value, PaymentStatus::CANCELLED->value])
                        ->whereHas('booking', fn ($booking) => $booking
                            ->where(function ($terminal) use ($graceHours): void {
                                $terminal->where(fn ($q) => $q
                                    ->where('status', BookingStatus::EXPIRED->value)
                                    ->where('expired_at', '>=', now()->subHours($graceHours)))
                                    ->orWhere(fn ($q) => $q
                                        ->where('status', BookingStatus::CANCELLED->value)
                                        ->where('cancelled_at', '>=', now()->subHours($graceHours)));
                            }));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $checked = 0;
        $failed = 0;

        foreach ($candidates as $payment) {
            try {
                $status = $client->fetchTransactionStatus($payment->provider_order_id);
            } catch (PaymentGatewayException $e) {
                $failed++;
                $this->warn("Gagal memeriksa {$payment->provider_order_id}: {$e->getMessage()}");

                continue;
            }

            $result = $payments->applyGatewayStatus($payment, $status);
            $checked++;

            $this->line("{$payment->provider_order_id}: {$result->value}");
        }

        $this->info("Diperiksa: {$checked}, gagal: {$failed}");

        return self::SUCCESS;
    }
}
