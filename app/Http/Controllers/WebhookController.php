<?php

namespace App\Http\Controllers;

use App\Models\WebhookReceipt;
use App\Services\GenerationManager;
use App\Services\PixelforgeApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

class WebhookController extends Controller
{
    public function store(Request $request, PixelforgeApi $api, GenerationManager $manager): JsonResponse
    {
        $raw = $request->getContent();
        abort_if(strlen($raw) > 100_000, 413);
        try {
            $verified = $api->verifyWebhook($raw, $request->header(config('pixelforge.signature_header')));
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Webhook verification not configured'], 503);
        }
        abort_unless($verified, 401);

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                return response()->json(['error' => 'Invalid payload'], 422);
            }
            if (($payload['type'] ?? null) === 'ping') {
                return response()->json(['received' => true], 200);
            }
            if (! is_string($payload['id'] ?? null) || ! str_starts_with($payload['id'], 'evt_')) {
                return response()->json(['error' => 'Missing event ID'], 422);
            }
            $event = $api->normalize($payload);
            if (empty($event['provider_id'])) {
                return response()->json(['error' => 'Missing provider ID'], 422);
            }
        } catch (JsonException|\RuntimeException $e) {
            return response()->json(['error' => 'Unrecognized event'], 422);
        }

        // Unique event-ID hash + terminal row lock protect against duplicate delivery.
        $hash = hash('sha256', $payload['id']); 
        DB::table('webhook_receipts')->insertOrIgnore([
            'payload_sha256' => $hash, 'provider_id' => $event['provider_id'],
            'payload' => $raw, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $receipt = WebhookReceipt::where('payload_sha256', $hash)->firstOrFail();
        try {
            $manager->processReceipt($receipt);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Retry later'], 503);
        }

        return response()->json(['received' => true], 202);
    }
}
