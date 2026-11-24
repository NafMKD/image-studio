<?php

namespace App\Http\Controllers;

use App\Models\CreditEntry;
use App\Models\Generation;
use App\Services\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->fresh();
        $succeeded = Generation::where('user_id', $user->id)
            ->where('status', 'succeeded'); 
        $total = (int) (clone $succeeded)->sum('actual_charge_nanos');
        $entries = CreditEntry::where('user_id', $user->id)
            ->whereIn('type', ['topup', 'charge'])
            ->latest('id')
            ->limit(30)
            ->get();

        return Inertia::render('Account', [
            'balance' => Money::display($user->creadit_balance_nanos),
            'available' => Money::display($user->credit_balance_nanos - $user->credit_reserved_nanos),
            'spent' => Money::display($total),
            'succeeded' => $succeeded->count(),
            'history' => $entries->map(fn ($e) => [
                'id' => $e->id, 
                'type' => $e->type,
                'amount' => Money::display($e->balance_delta_nanos),
                'date' => $e->created_at->toDateTimeString(),
            ]),
        ]);

    }
}
