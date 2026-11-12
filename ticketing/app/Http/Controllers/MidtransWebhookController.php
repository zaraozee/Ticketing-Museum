<?php

namespace App\Http\Controllers;

use App\Services\Payment\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Penerima notifikasi Midtrans (FR-PAY-02, THIRD_PARTY §1.3).
 *
 * - Tanpa CSRF dan tanpa sesi (dikecualikan di `bootstrap/app.php`).
 * - Signature diverifikasi lebih dulu; tidak sah → 403 dan tidak ada
 *   perubahan status (AC-3 Fase 5).
 * - Idempoten: notifikasi yang sama hanya diproses sekali (AC-2).
 */
class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        if (! is_string($payload['order_id'] ?? null)) {
            Log::warning('payment.webhook_malformed', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Payload tidak lengkap.'], 400);
        }

        // Dijalankan sinkron: gateway butuh kepastian status sebelum
        // menganggap notifikasi selesai. Bila antrean dikonfigurasi, job tetap
        // aman dijalankan langsung karena seluruh verifikasi ada di processor.
        $result = $processor->handle($payload);

        if ($result->value === 'rejected_signature') {
            return response()->json(['message' => 'Signature tidak sah.'], 403);
        }

        // Selalu 200 untuk payload lain agar gateway tidak mengirim ulang
        // tanpa henti; hasil pemrosesan tercatat di `payment_events`.
        return response()->json(['message' => 'OK', 'result' => $result->value]);
    }
}
