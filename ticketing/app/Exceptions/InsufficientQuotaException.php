<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Kuota tidak mencukupi saat membuat booking (AC-1 Fase 5, §12.1 langkah 10).
 *
 * Dokumen menuntut respons **409** untuk kondisi ini, bukan redirect biasa:
 * dua pengguna yang memperebutkan kuota terakhir harus dapat dibedakan mana
 * yang berhasil dan mana yang ditolak.
 */
class InsufficientQuotaException extends RuntimeException
{
    public function __construct(
        public readonly int $remaining = 0,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : 'Kuota tidak mencukupi.');
    }

    /**
     * Render exception menjadi respons 409.
     *
     * Permintaan JSON (termasuk pengujian HTTP) menerima 409 + pesan.
     * Permintaan Inertia juga menerima 409 agar kontraknya sama; frontend
     * menampilkannya sebagai kesalahan yang dapat dicoba ulang.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $payload = [
            'message' => $this->getMessage(),
            'remaining' => $this->remaining,
        ];

        if ($request->expectsJson() || $request->header('X-Inertia') !== null) {
            return response()->json($payload, 409);
        }

        // Browser biasa: kembalikan ke form dengan pesan, tanpa membuat booking.
        return back()
            ->withInput()
            ->withErrors(['quantity' => $this->getMessage()])
            ->setStatusCode(409);
    }
}
