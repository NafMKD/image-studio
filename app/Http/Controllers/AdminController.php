<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\User;
use App\Services\Money;
use App\Services\PixelforgeApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        $rows = Generation::query()
            ->join('users', 'generations.user_id', '=', 'users.id')
            ->where('generations.status', 'succeeded')
            ->selectRaw('DATE(generations.completed_at) as day, users.id as user_id, users.email, COUNT(*) as jobs, SUM(generations.provider_cost_nanos) as provider, SUM(generations.actual_charge_nanos) as charged')
            ->groupBy(DB::raw('DATE(generations.completed_at)'), 'users.id', 'users.email')
            ->orderByDesc('day')->get();

        return Inertia::render('Admin', [
            'rows' => $rows->map(fn ($r) => [
                'day' => $r->day, 'user' => $r->email, 'jobs' => $r->jobs,
                'provider' => Money::display((int) $r->provider),
                'charged' => Money::display((int) $r->charged),
                'profit' => Money::display((int) $r->charged - (int) $r->provider),
            ]),
            'users' => User::count(), 'generations' => Generation::count(),
            'pending' => Generation::whereNotIn('status', ['succeeded', 'failed'])->count(),
            'review' => Generation::query()->join('users', 'generations.user_id', '=', 'users.id')
                ->where('generations.status', 'uncertain')
                ->orderByDesc('generations.id')->limit(40)
                ->get(['generations.id', 'generations.client_request_id', 'users.email', 'generations.created_at']),
        ]);
    }

    public function usage(Request $request, PixelforgeApi $api): JsonResponse
    {
        // Admin only; this is the provider's RAW response until its schema is verified.
        return response()->json($api->usage());
    }
}
