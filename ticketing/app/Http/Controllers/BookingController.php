<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\Payment;
use App\Models\VisitSlot;
use App\Services\Booking\BookingService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alur pemesanan pengunjung: checkout, detail, pembayaran, riwayat
 * (FR-BKG-01…09, FR-PAY-05).
 *
 * Status pembayaran **hanya** dibaca di sini; seluruh mutasi berada di
 * `PaymentService`/`WebhookProcessor` (BR-10).
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Halaman checkout untuk satu museum (FR-BKG-01…05).
     */
    public function create(Museum $museum): Response
    {
        abort_unless($museum->status->isPubliclyVisible(), 404);

        return Inertia::render('Public/Bookings/Create', [
            'museum' => [
                'slug' => $museum->slug,
                'name' => $museum->name,
                'booking_mode' => $museum->booking_mode->value,
                'max_tickets_per_booking' => $museum->max_tickets_per_booking,
            ],
            'ticketTypes' => $museum->ticketTypes()
                ->active()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($ticketType): array => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'price' => $ticketType->price,
                    'price_label' => $ticketType->formattedPrice(),
                ])
                ->values(),
            // Prefill dari profil agar pengunjung tidak mengetik ulang.
            'contact' => [
                'name' => auth()->user()?->name,
                'email' => auth()->user()?->email,
                'phone' => auth()->user()?->phone,
            ],
        ]);
    }

    /**
     * Buat booking dan tahan kuota (FR-BKG-06, BR-05).
     */
    public function store(StoreBookingRequest $request, Museum $museum): RedirectResponse
    {
        abort_unless($museum->status->isPubliclyVisible(), 404);

        $data = $request->validated();

        $slot = VisitSlot::query()
            ->where('museum_id', $museum->id)
            ->whereKey((int) $data['visit_slot_id'])
            ->firstOrFail();

        $booking = $this->bookings->create(
            user: $request->user(),
            museum: $museum,
            slot: $slot,
            items: $data['items'],
            contactName: $data['contact_name'],
            contactEmail: $data['contact_email'],
            contactPhone: $data['contact_phone'],
        );

        return Redirect::route('bookings.show', $booking->uuid)
            ->with('success', 'Pemesanan dibuat. Selesaikan pembayaran sebelum batas waktu.');
    }

    /**
     * Detail booking + status pembayaran (FR-BKG-08, FR-PAY-05).
     *
     * Halaman ini **tidak** mengubah status apa pun, walau dibuka dari
     * redirect gateway (AC-8 Fase 5).
     */
    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        $booking->load(['items', 'payments', 'museum', 'tickets']);

        /** @var Payment|null $payment */
        $payment = $booking->payments->last();

        return Inertia::render('Public/Bookings/Show', [
            'booking' => [
                'uuid' => $booking->uuid,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'total_amount' => $booking->total_amount,
                'total_amount_label' => 'Rp '.number_format($booking->total_amount, 0, ',', '.'),
                'museum_name' => $booking->museum_name_snapshot,
                'museum_slug' => $booking->museum?->slug,
                'visit_date' => $booking->visit_date_snapshot->toDateString(),
                'slot_label' => $booking->slot_label_snapshot,
                'contact_name' => $booking->contact_name,
                'contact_email' => $booking->contact_email,
                'contact_phone' => $booking->contact_phone,
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'seconds_until_expiry' => $booking->secondsUntilExpiry(),
                'is_payable' => $booking->isPayable(),
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
                'items' => $booking->items->map(fn (BookingItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->ticket_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_price_label' => 'Rp '.number_format($item->unit_price, 0, ',', '.'),
                    'subtotal' => $item->subtotal,
                    'subtotal_label' => 'Rp '.number_format($item->subtotal, 0, ',', '.'),
                ])->values(),
                'ticket_count' => $booking->tickets->count(),
                'payment' => $payment === null ? null : [
                    'status' => $payment->status->value,
                    'status_label' => $payment->status->label(),
                    'gross_amount' => $payment->gross_amount,
                    'snap_token' => $payment->snap_token,
                    // Cadangan bila skrip Snap gagal dimuat (mis. diblokir
                    // jaringan kantor): pengguna tetap dapat menyelesaikan
                    // pembayaran lewat halaman Midtrans.
                    'snap_redirect_url' => $payment->snap_redirect_url,
                    'needs_review' => $payment->needs_review,
                ],
            ],
            // Token Snap boleh dikirim ke browser; Server Key tidak pernah.
            'snapClientKey' => config('services.midtrans.client_key'),
            'snapUrl' => config('services.midtrans.snap_url'),
        ]);
    }

    /**
     * Mulai/lanjutkan pembayaran (FR-BKG-09, FR-PAY-01).
     */
    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        $this->payments->start($booking);

        return Redirect::route('bookings.show', $booking->uuid);
    }

    /**
     * Batalkan booking `pending_payment` (FR-BKG-07, BR-08).
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('view', $booking);

        $cancelled = $this->bookings->cancel($booking, $request->user());

        return Redirect::route('bookings.show', $booking->uuid)
            ->with(
                $cancelled ? 'success' : 'error',
                $cancelled
                    ? 'Pemesanan dibatalkan dan kuota dilepas.'
                    : 'Pemesanan ini tidak dapat dibatalkan.',
            );
    }

    /**
     * Riwayat booking pengguna (FR-BKG-08).
     */
    public function index(Request $request): Response
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->through(fn (Booking $booking): array => [
                'uuid' => $booking->uuid,
                'booking_code' => $booking->booking_code,
                'museum_name' => $booking->museum_name_snapshot,
                'visit_date' => $booking->visit_date_snapshot->toDateString(),
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'total_amount_label' => 'Rp '.number_format($booking->total_amount, 0, ',', '.'),
                'created_at' => $booking->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Public/Bookings/Index', [
            'bookings' => $bookings,
            'statuses' => collect(BookingStatus::cases())
                ->map(fn (BookingStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
        ]);
    }

    /**
     * Polling status pembayaran untuk halaman status (FR-PAY-05).
     *
     * Baca-saja: tidak pernah menerima status dari klien (AC-8).
     */
    public function status(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        /** @var Payment|null $payment */
        $payment = $booking->payments()->orderByDesc('id')->first();

        return response()->json([
            'booking_status' => $booking->status->value,
            'booking_status_label' => $booking->status->label(),
            'payment_status' => $payment?->status->value,
            'payment_status_label' => $payment?->status->label(),
            'needs_review' => (bool) $payment?->needs_review,
            'ticket_count' => $booking->tickets()->count(),
            'seconds_until_expiry' => $booking->secondsUntilExpiry(),
        ]);
    }
}
