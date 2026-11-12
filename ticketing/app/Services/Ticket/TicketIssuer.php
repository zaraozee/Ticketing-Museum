<?php

namespace App\Services\Ticket;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Ticket;
use RuntimeException;

/**
 * Penerbitan tiket saat booking dikonfirmasi (BR-07, D-29).
 *
 * **Idempotensi.** Konfirmasi pembayaran dapat dipicu ulang oleh webhook
 * duplikat atau retry job. Karena itu penerbitan tiket tidak boleh
 * menggandakan baris: kombinasi `(booking_item_id, sequence)` unik, dan
 * penerbit memeriksa tiket yang sudah ada lebih dulu.
 *
 * **Token (SYSTEM_DESIGN §11.3, D-34).** Format `MT1.<kid>.<uuid>.<sig>`;
 * hanya `sha256(token)` yang disimpan (ERD §3.5), sehingga e-ticket dirender
 * dengan menghitung ulang token dari `uuid` — bukan dibaca dari database.
 */
class TicketIssuer
{
    /**
     * Terbitkan tiket untuk seluruh item booking. Aman dipanggil berulang.
     *
     * Harus dipanggil di dalam transaksi yang sama dengan perubahan status
     * booking dan perpindahan kuota (BR-07).
     *
     * @return int jumlah tiket yang benar-benar dibuat pada pemanggilan ini
     */
    public function issueFor(Booking $booking): int
    {
        $created = 0;

        $items = BookingItem::query()
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            for ($sequence = 1; $sequence <= $item->quantity; $sequence++) {
                $exists = Ticket::query()
                    ->where('booking_item_id', $item->id)
                    ->where('sequence', $sequence)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // UUID v4 dari CSPRNG (ERD §5 langkah 1). Kolomnya bertipe
                // `uuid` di PostgreSQL, jadi ULID tidak dapat dipakai.
                $uuid = (string) str()->uuid();
                $token = $this->tokenFor($uuid);

                Ticket::create([
                    'uuid' => $uuid,
                    'ticket_code' => Ticket::generateTicketCode(),
                    'token_hash' => $this->hashToken($token),
                    'booking_id' => $booking->id,
                    'booking_item_id' => $item->id,
                    'sequence' => $sequence,
                    'museum_id' => $booking->museum_id,
                    'visit_slot_id' => $booking->visit_slot_id,
                    'holder_name' => $booking->contact_name,
                    'status' => 'active',
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Hitung token QR dari uuid tiket (SYSTEM_DESIGN §11.3).
     *
     * `MT1.<kid>.<uuid>.<sig>` dengan
     * `sig = base64url(HMAC-SHA256(key[kid], "MT1." + uuid))` dipotong 22
     * karakter pertama.
     */
    public function tokenFor(string $ticketUuid): string
    {
        $kid = $this->activeKid();
        $key = $this->key($kid);

        $prefix = 'MT1.'.$kid.'.'.$ticketUuid;
        $mac = hash_hmac('sha256', 'MT1.'.$ticketUuid, $key, true);

        $signature = substr($this->base64UrlEncode($mac), 0, $this->signatureLength());

        return $prefix.'.'.$signature;
    }

    /**
     * Hitung ulang token untuk tiket tersimpan (dipakai halaman e-ticket).
     */
    public function tokenForTicket(Ticket $ticket): string
    {
        return $this->tokenFor($ticket->uuid);
    }

    /**
     * Hash token untuk kolom `tickets.token_hash` (kunci lookup saat scan).
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * `kid` yang dipakai untuk tiket baru.
     */
    public function activeKid(): string
    {
        $kid = config('services.ticket.active_kid');

        if (! is_string($kid) || $kid === '') {
            throw new RuntimeException('TICKET_ACTIVE_KID belum diatur.');
        }

        return $kid;
    }

    /**
     * Rahasia HMAC untuk satu `kid`. Tidak pernah memakai nilai default lemah:
     * tanpa rahasia, tiket tidak boleh diterbitkan sama sekali.
     */
    private function key(string $kid): string
    {
        /** @var array<string, mixed> $keys */
        $keys = (array) config('services.ticket.keys', []);
        $key = $keys[$kid] ?? null;

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                "Kunci tiket \"{$kid}\" tidak ditemukan. Atur TICKET_SIGNING_KEYS pada environment.",
            );
        }

        return $key;
    }

    private function signatureLength(): int
    {
        return max(16, (int) config('services.ticket.signature_length', 22));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
