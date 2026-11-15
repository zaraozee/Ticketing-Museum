<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user?->organizerProfile;

        $museums = Museum::query()
            ->where('organizer_profile_id', $profile?->id)
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        $museumId = $request->get('museum_id', $museums->first()?->id);
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Paginator kosong menjaga bentuk respons tetap sama saat pengelola
        // belum punya museum, sehingga komponen Vue tidak perlu dua cabang.
        $bookings = new LengthAwarePaginator([], 0, 20);
        $visitors = collect();
        $revenue = 0;
        $ticketCount = 0;

        if ($museumId) {
            // Kepemilikan museum diverifikasi ulang: `museum_id` berasal dari
            // query string dan tidak boleh membocorkan data museum lain (AC-2).
            $owned = $museums->contains('id', (int) $museumId);

            abort_unless($owned, 403);

            $bookings = Booking::where('museum_id', $museumId)
                ->where('status', BookingStatus::CONFIRMED)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->with(['museum:id,name', 'user:id,name,email', 'items.ticketType:id,name'])
                ->orderByDesc('created_at')
                ->paginate(20)
                ->through(fn (Booking $b): array => [
                    'id' => $b->id,
                    'code' => $b->booking_code,
                    'user_name' => $b->user->name,
                    'user_email' => $b->user->email,
                    'status' => $b->status->value,
                    'total_amount' => $b->total_amount,
                    'visit_date' => $b->visit_date_snapshot->format('d/m/Y'),
                    'created_at' => $b->created_at->format('d/m/Y H:i'),
                ]);

            $revenue = Booking::where('museum_id', $museumId)
                ->where('status', BookingStatus::CONFIRMED)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('total_amount');

            $ticketCount = Ticket::where('museum_id', $museumId)
                ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::CONFIRMED))
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->count();

            $visitors = Ticket::where('museum_id', $museumId)
                ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::CONFIRMED))
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->with(['booking.user:id,name,email', 'bookingItem:id,ticket_name_snapshot'])
                ->orderBy('status')
                ->limit(100)
                ->get()
                ->map(fn (Ticket $t): array => [
                    'id' => $t->id,
                    'code' => $t->ticket_code,
                    'status' => $t->status->value,
                    'visitor_name' => $t->booking->user->name,
                    'visitor_email' => $t->booking->user->email,
                    'ticket_type' => $t->bookingItem->ticket_name_snapshot,
                    'visit_date' => $t->booking->visit_date_snapshot->format('d/m/Y'),
                    'used_at' => $t->used_at?->format('d/m/Y H:i'),
                ]);
        }

        return Inertia::render('Organizer/Reports/Index', [
            'museums' => $museums,
            'selectedMuseum' => $museumId ? (int) $museumId : null,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'bookings' => $bookings,
            'visitors' => $visitors,
            'revenue' => $revenue,
            'ticketCount' => $ticketCount,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $profile = $user?->organizerProfile;

        $museumId = $request->get('museum_id');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        /*
         * Kepemilikan diverifikasi ulang: `museum_id` berasal dari query string
         * dan berkas ini memuat data pribadi pemesan (AC-2).
         *
         * `$profile?->id` sengaja dipakai, bukan `$profile->id`: pengelola yang
         * belum punya profil organisasi adalah keadaan yang sah — halaman
         * laporannya sendiri sudah menanganinya dengan paginator kosong.
         * Memakai `$profile->id` membuat kasus itu berakhir sebagai 500,
         * bukan 404.
         */
        $museum = Museum::query()
            ->where('id', $museumId)
            ->where('organizer_profile_id', $profile?->id)
            ->firstOrFail();

        $bookings = Booking::where('museum_id', $museum->id)
            ->where('status', BookingStatus::CONFIRMED)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->with(['user:id,name,email', 'items'])
            ->orderBy('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="laporan_'.$museum->slug.'_'.$startDate.'_'.$endDate.'.csv"',
        ];

        $callback = function () use ($bookings): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8

            fputcsv($file, ['Kode Booking', 'Pemesan', 'Email', 'Tanggal Kunjungan', 'Status', 'Jumlah Tiket', 'Total (Rp)', 'Dibuat Pada']);

            foreach ($bookings as $b) {
                /** @var Booking $b */
                fputcsv($file, [
                    $b->booking_code,
                    $b->user->name,
                    $b->user->email,
                    $b->visit_date_snapshot->format('Y-m-d'),
                    $b->status->value,
                    $b->items->sum('quantity'),
                    $b->total_amount,
                    $b->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
