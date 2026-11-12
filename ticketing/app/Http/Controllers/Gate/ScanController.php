<?php

namespace App\Http\Controllers\Gate;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\TicketScan;
use App\Services\Gate\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gate Scanner API (FR-GATE-01...06).
 *
 * Endpoint ini diakses oleh petugas gate (role user + museum_staff aktif).
 */
class ScanController extends Controller
{
    public function __construct(
        private readonly ScanService $scanService,
    ) {}

    /**
     * Daftar museum yang dapat dipindai oleh petugas ini.
     *
     * GET /api/v1/gate/museums
     */
    public function museums(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin dapat memindai semua museum
        if ($user->hasRole('admin')) {
            $museums = Museum::select('id', 'name', 'slug')
                ->where('status', 'published')
                ->orderBy('name')
                ->get();

            return response()->json(['museums' => $museums]);
        }

        // Organizer: museum miliknya
        $orgMuseums = Museum::select('id', 'name', 'slug')
            ->where('organizer_profile_id', $user->organizerProfile?->id)
            ->where('status', 'published')
            ->orderBy('name')
            ->get();

        // User biasa: museum di mana mereka adalah staf aktif
        $staffMuseums = $user->museumStaff()
            ->where('is_active', true)
            ->with('museum:id,name,slug,status')
            ->get()
            ->pluck('museum')
            ->filter(fn ($m) => $m && $m->status->value === 'published')
            ->values();

        $museums = $orgMuseums->merge($staffMuseums)->unique('id')->values();

        return response()->json(['museums' => $museums]);
    }

    /**
     * Scan tiket via QR atau kode manual.
     *
     * POST /api/v1/gate/scans
     *
     * Body:
     * - museum_id (required)
     * - qr_payload (optional) — token QR
     * - ticket_code (optional) — kode manual
     * - client_scan_uuid (required) — untuk idempotensi
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'museum_id' => ['required', 'integer', 'exists:museums,id'],
            'qr_payload' => ['nullable', 'string', 'max:200'],
            'ticket_code' => ['nullable', 'string', 'size:10'],
            'client_scan_uuid' => ['required', 'uuid'],
        ]);

        if (! $validated['qr_payload'] && ! $validated['ticket_code']) {
            throw ValidationException::withMessages([
                'qr_payload' => 'Salah satu dari qr_payload atau ticket_code harus diisi.',
            ]);
        }

        $user = $request->user();
        $museumId = $validated['museum_id'];

        // Verifikasi akses petugas ke museum ini
        if (! $this->canScanMuseum($user, $museumId)) {
            return response()->json([
                'error' => 'Anda tidak memiliki akses untuk memindai tiket museum ini.',
            ], 403);
        }

        $result = $this->scanService->scan(
            museumId: (string) $museumId,
            qrPayload: $validated['qr_payload'] ?? null,
            ticketCode: $validated['ticket_code'] ?? null,
            clientScanUuid: $validated['client_scan_uuid'],
            scannedBy: $user->id
        );

        return response()->json([
            'result' => $result['result']->value,
            'label' => $result['result']->label(),
            'color' => $result['result']->color(),
            'allows_entry' => $result['result']->allowsEntry(),
            'message' => $result['message'],
            'ticket' => $result['ticket'] ? [
                'uuid' => $result['ticket']->uuid,
                'code' => $result['ticket']->ticket_code,
                'type' => $result['ticket']->bookingItem->ticket_name_snapshot,
                'holder' => $result['ticket']->bookingItem->booking->contact_name,
                'visit_date' => $result['ticket']->bookingItem->booking->visitSlot->visit_date,
                'slot_time' => $result['ticket']->bookingItem->booking->visitSlot->start_time
                    ? $result['ticket']->bookingItem->booking->visitSlot->start_time.' - '.$result['ticket']->bookingItem->booking->visitSlot->end_time
                    : 'Sepanjang hari',
            ] : null,
            'scan_id' => $result['scan']->id,
            'scanned_at' => $result['scan']->scanned_at->toIso8601String(),
        ]);
    }

    /**
     * Riwayat scan per museum.
     *
     * GET /api/v1/gate/scans?museum_id=1
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'museum_id' => ['required', 'integer', 'exists:museums,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $museumId = $validated['museum_id'];

        if (! $this->canScanMuseum($user, $museumId)) {
            return response()->json([
                'error' => 'Anda tidak memiliki akses untuk melihat riwayat scan museum ini.',
            ], 403);
        }

        $limit = $validated['limit'] ?? 50;

        $scans = TicketScan::where('museum_id', $museumId)
            ->with(['ticket.bookingItem.booking', 'scannedByUser:id,name'])
            ->orderByDesc('scanned_at')
            ->limit($limit)
            ->get()
            ->map(fn ($scan) => [
                'id' => $scan->id,
                'result' => $scan->result->value,
                'label' => $scan->result->label(),
                'color' => $scan->result->color(),
                'scanned_at' => $scan->scanned_at->toIso8601String(),
                'scanned_by' => $scan->scannedByUser?->name,
                'ticket_code' => $scan->ticket?->ticket_code,
                'holder' => $scan->ticket?->bookingItem?->booking?->contact_name,
                'notes' => $scan->notes,
            ]);

        return response()->json(['scans' => $scans]);
    }

    /**
     * Cek apakah user dapat memindai museum tertentu.
     */
    private function canScanMuseum($user, int $museumId): bool
    {
        // Admin dapat memindai semua
        if ($user->hasRole('admin')) {
            return true;
        }

        // Organizer owner museum
        $museum = Museum::find($museumId);
        if ($museum && $museum->organizer_profile_id === $user->organizerProfile?->id) {
            return true;
        }

        // Staf aktif di museum ini
        return $user->museumStaff()
            ->where('museum_id', $museumId)
            ->where('is_active', true)
            ->exists();
    }
}
