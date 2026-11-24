<?php

namespace App\Http\Controllers;

use App\Models\CreditEntry;
use App\Models\User;
use App\Services\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $amount = Money::fromUsd('10.00');
            $user->increment('credit_balance_nanos', $amount);
            CreditEntry::create([
                'user_id' => $user->id, 'event_key' => 'demo-topup:'.Str::uuid(),
                'type' => 'topup', 'balance_delta_nanos' => $amount, 'reserved_delta_nanos' => 0,
            ]);
        }, attempts: 3);

        return back()->with('success', 'Added $10 demo credits. No payment was made.');
    }
}
