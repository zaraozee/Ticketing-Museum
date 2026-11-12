<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnomalyController extends Controller
{
    public function index(Request $request): Response
    {
        $anomalies = Payment::where('needs_review', true)
            ->orWhere(function ($query) {
                $query->where('status', 'paid')
                    ->whereHas('booking', function ($q) {
                        $q->whereIn('status', ['expired', 'cancelled']);
                    });
            })
            ->with(['booking.user:id,name,email', 'booking.museum:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($payment) => [
                'id' => $payment->id,
                'booking_code' => $payment->booking->booking_code,
                'museum_name' => $payment->booking->museum->name,
                'user_name' => $payment->booking->user->name,
                'user_email' => $payment->booking->user->email,
                'gross_amount' => $payment->gross_amount,
                'status' => $payment->status->value,
                'needs_review' => $payment->needs_review,
                'created_at' => $payment->created_at->format('d/m/Y H:i'),
                'provider_order_id' => $payment->provider_order_id,
            ]);

        return Inertia::render('Admin/Anomalies/Index', [
            'anomalies' => $anomalies,
        ]);
    }

    public function resolve(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:confirm_refund,mark_resolved'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'confirm_refund') {
            $payment->update([
                'needs_review' => false,
                'review_note' => $validated['notes'] ?? 'Refund dikonfirmasi oleh admin.',
            ]);
        } else {
            $payment->update([
                'needs_review' => false,
                'review_note' => $validated['notes'] ?? 'Anomali ditandai selesai.',
            ]);
        }

        return back()->with('success', 'Anomali berhasil ditandai selesai.');
    }
}
