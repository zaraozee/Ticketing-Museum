<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Museum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $museums = Museum::select('id', 'name', 'slug')
            ->where('status', 'published')
            ->orderBy('name')
            ->get();

        $query = Booking::where('status', BookingStatus::CONFIRMED)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->with(['museum:id,name', 'user:id,name,email']);

        $museumId = $request->get('museum_id');
        if ($museumId) {
            $query->where('museum_id', $museumId);
        }

        $bookings = $query->latest()
            ->paginate(20)
            ->through(fn (Booking $b): array => [
                'id' => $b->id,
                'code' => $b->booking_code,
                'museum_name' => $b->museum->name,
                'user_name' => $b->user->name,
                'user_email' => $b->user->email,
                'total_amount' => $b->total_amount,
                'visit_date' => $b->visit_date_snapshot->format('d/m/Y'),
                'created_at' => $b->created_at->format('d/m/Y H:i'),
            ]);

        $totalRevenue = Booking::where('status', BookingStatus::CONFIRMED)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($museumId, fn ($q) => $q->where('museum_id', $museumId))
            ->sum('total_amount');

        $totalBookings = Booking::where('status', BookingStatus::CONFIRMED)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->when($museumId, fn ($q) => $q->where('museum_id', $museumId))
            ->count();

        /*
         * Pendapatan per museum.
         *
         * `HAVING` pada kolom agregat hasil `withSum` ditolak SQLite ("HAVING
         * clause on a non-aggregate query") dan perilakunya berbeda antar
         * driver. Penyaringan nilai nol dilakukan di PHP agar portabel ke
         * SQLite (dev/test) maupun PostgreSQL (produksi).
         */
        $revenueByMuseum = Museum::select('id', 'name')
            ->withSum(['bookings' => fn ($q) => $q->where('status', BookingStatus::CONFIRMED)
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']),
            ], 'total_amount')
            ->orderByDesc('bookings_sum_total_amount')
            ->get()
            ->filter(fn (Museum $m): bool => (int) $m->getAttribute('bookings_sum_total_amount') > 0)
            ->take(10)
            ->map(fn (Museum $m): array => [
                'name' => $m->name,
                'revenue' => (int) $m->getAttribute('bookings_sum_total_amount'),
            ])
            ->values();

        return Inertia::render('Admin/Reports/Index', [
            'museums' => $museums,
            'bookings' => $bookings,
            'totalRevenue' => $totalRevenue,
            'totalBookings' => $totalBookings,
            'revenueByMuseum' => $revenueByMuseum,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedMuseum' => $museumId ? (int) $museumId : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $query = Booking::where('status', BookingStatus::CONFIRMED)
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->with(['museum:id,name', 'user:id,name,email', 'items']);

        if ($request->get('museum_id')) {
            $query->where('museum_id', $request->get('museum_id'));
        }

        $bookings = $query->orderBy('created_at')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="laporan_platform_'.$startDate.'_'.$endDate.'.csv"',
        ];

        $callback = function () use ($bookings): void {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Kode Booking', 'Museum', 'Pemesan', 'Email', 'Tanggal Kunjungan', 'Jumlah Tiket', 'Total (Rp)', 'Dibuat Pada']);

            foreach ($bookings as $b) {
                /** @var Booking $b */
                fputcsv($file, [
                    $b->booking_code,
                    $b->museum->name,
                    $b->user->name,
                    $b->user->email,
                    $b->visit_date_snapshot->format('Y-m-d'),
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
