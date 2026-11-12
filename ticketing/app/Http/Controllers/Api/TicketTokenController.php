<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Ticket\TicketTokenSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTokenController extends Controller
{
    public function __construct(
        private readonly TicketTokenSigner $signer
    ) {}

    public function show(Request $request, string $uuid): JsonResponse
    {
        $ticket = Ticket::where('uuid', $uuid)
            ->whereHas('booking', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $qrToken = $this->signer->sign($ticket->uuid);

        return response()->json([
            'qr_token' => $qrToken,
            'ticket_code' => $ticket->ticket_code,
            'status' => $ticket->status->value,
        ]);
    }
}
