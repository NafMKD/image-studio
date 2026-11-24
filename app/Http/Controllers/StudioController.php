<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Money;
use App\Services\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    public function show(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();
        abort_if($conversation && $conversation->user_id !== $user->id, 404);

        $messages = $conversation?->generations()->orderBy('id')->get()->map(fn ($generation) => [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'status' => $generation->status,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'image_url' => $generation->image_url,
            'error' => $generation->error_message,
            'estimated' => Money::display($generation->estimated_charge_nanos),
            'actual' => $generation->actual_charge_nanos === null ? null : Money::display($generation->actual_charge_nanos),
        ]) ?? collect();

        $fresh = $user->fresh();
        return Inertia::render('Studio', [
            'chats' => $user->conversations()->latest('updated_at')->limit(60)->get(['id', 'title']),
            'conversation' => $conversation ? ['id' => $conversation->id, 'messages' => $messages] : null,
            'wallet' => [
                'balance' => Money::display($fresh->credit_balance_nanos),
                'available' => Money::display($fresh->credit_balance_nanos - $fresh->credit_reserved_nanos),
            ],
            'isAdmin' => (bool) $user->is_admin,
            'demoMode' => config('pixelforge.driver') === 'fake',
        ]);
    }

    public function estimate(Request $request, Pricing $pricing): JsonResponse
    {
        $data = $request->validate([
            'characters' => ['required', 'integer', 'between:0,4000'],
            'references' => ['required', 'integer', 'between:0,4'],
            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
            'quality' => ['required', 'in:low,medium,high'],
        ]);
        $quote = $pricing->quote($data['characters'], $data['size'], $data['quality'], $data['references']);

        return response()->json([
            'estimated' => Money::display($quote['estimated']),
            'hold' => Money::display($quote['reserved']),
        ]);
    }
}
