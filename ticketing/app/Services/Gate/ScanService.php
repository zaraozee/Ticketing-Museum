<?php

namespace App\Services\Gate;

use App\Enums\ScanResult;
use App\Enums\TicketStatus;
use App\Models\Museum;
use App\Models\Ticket;
use App\Models\TicketScan;
use App\Models\User;
use App\Services\Ticket\TicketTokenSigner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Validasi dan scan tiket di gerbang (BR-14, BR-16).
 *
 * Urutan pemeriksaan:
 * 1. Token valid (signature)
 * 2. Museum sesuai
 * 3. Tidak void
 * 4. Belum used
 * 5. Jendela waktu valid
 * 6. Tandai used atomik (UPDATE WHERE status='active')
 *
 * Idempotensi: client_scan_uuid mencegah retry dianggap double scan.
 */
class ScanService
{
    public function __construct(
        private readonly TicketTokenSigner $signer,
    ) {}

    /**
     * Scan tiket via QR token atau kode manual.
     *
     * @param  string  $museumId  ID museum tempat scan
     * @param  string|null  $qrPayload  Token QR (MT1.kid.uuid.sig)
     * @param  string|null  $ticketCode  Kode manual (10 char)
     * @param  string  $clientScanUuid  UUID unik dari client untuk idempotensi
     * @param  int  $scannedBy  User ID petugas
     * @return array{result: ScanResult, ticket: Ticket|null, scan: TicketScan|null, message: string}
     */
    public function scan(
        string $museumId,
        ?string $qrPayload,
        ?string $ticketCode,
        string $clientScanUuid,
        int $scannedBy
    ): array {
        // Idempotensi: cek apakah scan dengan UUID ini sudah ada
        $existing = TicketScan::where('client_scan_uuid', $clientScanUuid)->first();

        if ($existing) {
            return [
                'result' => $existing->result,
                'ticket' => $existing->ticket,
                'scan' => $existing,
                'message' => 'Hasil scan sebelumnya (retry).',
            ];
        }

        // Resolve ticket dari QR atau kode
        $ticket = $this->resolveTicket($qrPayload, $ticketCode);

        if (! $ticket) {
            return $this->recordScan(
                null,
                $museumId,
                ScanResult::INVALID,
                $clientScanUuid,
                $scannedBy,
                'Token atau kode tidak valid.'
            );
        }

        // Validasi museum
        if ($ticket->booking->museum_id !== (int) $museumId) {
            return $this->recordScan(
                $ticket,
                $museumId,
                ScanResult::WRONG_MUSEUM,
                $clientScanUuid,
                $scannedBy,
                'Tiket untuk museum lain.'
            );
        }

        // Validasi status void
        if ($ticket->status === TicketStatus::VOID) {
            return $this->recordScan(
                $ticket,
                $museumId,
                ScanResult::VOIDED,
                $clientScanUuid,
                $scannedBy,
                'Tiket dibatalkan.'
            );
        }

        // Validasi already used
        if ($ticket->status === TicketStatus::USED) {
            return $this->recordScan(
                $ticket,
                $museumId,
                ScanResult::ALREADY_USED,
                $clientScanUuid,
                $scannedBy,
                'Tiket sudah digunakan pada '.optional($ticket->used_at)->format('d/m/Y H:i').'.'
            );
        }

        // Validasi expired
        if ($ticket->status === TicketStatus::EXPIRED) {
            return $this->recordScan(
                $ticket,
                $museumId,
                ScanResult::EXPIRED,
                $clientScanUuid,
                $scannedBy,
                'Tiket kedaluwarsa.'
            );
        }

        // Validasi jendela waktu
        $timeCheck = $this->checkTimeWindow($ticket);

        if (! $timeCheck['valid']) {
            $result = $timeCheck['too_early'] ? ScanResult::NOT_YET_VALID : ScanResult::EXPIRED;

            return $this->recordScan(
                $ticket,
                $museumId,
                $result,
                $clientScanUuid,
                $scannedBy,
                $timeCheck['message']
            );
        }

        // Tandai used atomik (BR-14)
        $updated = DB::transaction(function () use ($ticket) {
            return Ticket::where('id', $ticket->id)
                ->where('status', TicketStatus::ACTIVE->value)
                ->update([
                    'status' => TicketStatus::USED->value,
                    'used_at' => now(),
                ]);
        });

        if ($updated !== 1) {
            // Race condition: tiket sudah diubah oleh scan lain
            $ticket->refresh();

            return $this->recordScan(
                $ticket,
                $museumId,
                ScanResult::ALREADY_USED,
                $clientScanUuid,
                $scannedBy,
                'Tiket sudah digunakan (race condition).'
            );
        }

        $ticket->refresh();

        return $this->recordScan(
            $ticket,
            $museumId,
            ScanResult::VALID,
            $clientScanUuid,
            $scannedBy,
            'Tiket valid. Silakan masuk.'
        );
    }

    /**
     * Resolve ticket dari QR payload atau kode manual.
     */
    private function resolveTicket(?string $qrPayload, ?string $ticketCode): ?Ticket
    {
        if ($qrPayload) {
            $verified = $this->signer->verify($qrPayload);

            if (! $verified['valid']) {
                return null;
            }

            return Ticket::where('uuid', $verified['ticket_uuid'])
                ->with(['booking.museum', 'booking.visitSlot'])
                ->first();
        }

        if ($ticketCode) {
            return Ticket::where('ticket_code', strtoupper($ticketCode))
                ->with(['booking.museum', 'booking.visitSlot'])
                ->first();
        }

        return null;
    }

    /**
     * Periksa apakah tiket dalam jendela waktu valid.
     *
     * Catatan zona waktu: `visit_slots.visit_date` disimpan sebagai tanggal
     * murni (UTC di database) dan dibaca sebagai `Carbon` oleh cast model.
     * Jangan mem-parse instance Carbon dengan parameter zona waktu — Carbon
     * mengabaikannya sehingga tanggal kunjungan tetap di UTC dan perbandingan
     * dengan `now()` di zona museum menjadi salah. Bangun ulang tanggal dari
     * string `Y-m-d` di zona waktu museum.
     *
     * @return array{valid: bool, too_early: bool, message: string}
     */
    private function checkTimeWindow(Ticket $ticket): array
    {
        $booking = $ticket->booking;
        $slot = $booking->visitSlot;
        $museum = $booking->museum;

        $timezone = $museum->timezone ?? 'Asia/Jakarta';

        $now = now()->timezone($timezone);
        $visitDate = Carbon::createFromFormat(
            'Y-m-d',
            $slot->visit_date->format('Y-m-d'),
            $timezone,
        )->startOfDay();

        $earlyMinutes = (int) config('ticketing.entry_early_minutes', 15);
        $lateMinutes = (int) config('ticketing.entry_late_minutes', 30);

        // Mode daily: berlaku sepanjang hari operasional
        if ($museum->booking_mode->value === 'daily') {
            // Ambil jam operasional hari ini
            $dayOfWeek = $visitDate->dayOfWeek; // 0=Sun, 1=Mon, ...
            $hours = $museum->operatingHours()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_closed', false)
                ->first();

            if (! $hours) {
                return ['valid' => false, 'too_early' => false, 'message' => 'Museum tutup pada tanggal ini.'];
            }

            $openTime = $visitDate->copy()->setTimeFromTimeString((string) $hours->open_time);
            $closeTime = $visitDate->copy()->setTimeFromTimeString((string) $hours->close_time);
        } else {
            // Mode timed_slot
            $openTime = $visitDate->copy()->setTimeFromTimeString((string) $slot->start_time);
            $closeTime = $visitDate->copy()->setTimeFromTimeString((string) $slot->end_time);
        }

        $validStart = $openTime->copy()->subMinutes($earlyMinutes);
        $validEnd = $closeTime->copy()->addMinutes($lateMinutes);

        if ($now->lt($validStart)) {
            return [
                'valid' => false,
                'too_early' => true,
                'message' => "Tiket berlaku mulai {$validStart->format('d/m/Y H:i')}.",
            ];
        }

        if ($now->gt($validEnd)) {
            return ['valid' => false, 'too_early' => false, 'message' => 'Tiket kedaluwarsa.'];
        }

        return ['valid' => true, 'too_early' => false, 'message' => ''];
    }

    /**
     * Catat hasil scan ke database.
     *
     * @return array{result: ScanResult, ticket: Ticket|null, scan: TicketScan, message: string}
     */
    private function recordScan(
        ?Ticket $ticket,
        string $museumId,
        ScanResult $result,
        string $clientScanUuid,
        int $scannedBy,
        string $message
    ): array {
        $scan = TicketScan::create([
            'ticket_id' => $ticket?->id,
            'museum_id' => $museumId,
            'scanned_by' => $scannedBy,
            'result' => $result->value,
            'client_scan_uuid' => $clientScanUuid,
            'scanned_at' => now(),
            'notes' => $message,
        ]);

        return [
            'result' => $result,
            'ticket' => $ticket,
            'scan' => $scan,
            'message' => $message,
        ];
    }
}
