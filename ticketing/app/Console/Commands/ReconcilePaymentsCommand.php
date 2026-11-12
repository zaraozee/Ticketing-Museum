<?php

namespace App\Console\Commands;

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
        {--limit=100 : Jumlah maksimum payment yang diperiksa}';

    protected $description = 'Periksa ulang status payment menggantung lewat API gateway';

    public function handle(MidtransClient $client, PaymentService $payments): int
    {
        if (! $client->isLive()) {
            $this->warn('Gateway tidak terkonfigurasi (MIDTRANS_SERVER_KEY kosong); rekonsiliasi dilewati.');

            return self::SUCCESS;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));

        $candidates = Payment::query()
            ->whereIn('status', [PaymentStatus::CREATED->value, PaymentStatus::PENDING->value])
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->where('needs_review', false)
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
