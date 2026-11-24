<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CreditEntry;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookReceipt;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class GenerationManager
{
    public function __construct(private readonly Pricing $pricing, private readonly PixelforgeApi $api) {}

    public function start(User $actor, array $input): Generation
    {
        if (! $this->api->ready()) {
            throw ValidationException::withMessages(['prompt' => 'Live API settings are not verified; no credits were reserved.']);
        }
        $quote = $this->pricing->quote(
            mb_strlen($input['prompt']), $input['size'], $input['quality'], count($input['reference_urls'] ?? [])
        );

        [$generation, $created] = DB::transaction(function () use ($actor, $input, $quote) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            $existing = Generation::query()->where('client_request_id', $input['client_request_id'])
                ->where('user_id', $user->id)->first();
            if ($existing) {
                return [$existing, false];
            }
            if ($user->credit_balance_nanos - $user->credit_reserved_nanos < $quote['reserved']) {
                throw ValidationException::withMessages(['prompt' => 'Not enough available credits. Add demo credits first.']);
            }

            $conversation = empty($input['conversation_id'])
                ? Conversation::create(['user_id' => $user->id, 'title' => Str::limit($input['prompt'], 54, '')])
                : Conversation::query()->where('user_id', $user->id)->findOrFail($input['conversation_id']);

            $generation = Generation::create([
                'user_id' => $user->id, 'conversation_id' => $conversation->id,
                'client_request_id' => $input['client_request_id'], 'prompt' => $input['prompt'],
                'size' => $input['size'], 'quality' => $input['quality'],
                'reference_urls' => $input['reference_urls'] ?? [],
                'status' => 'submitting', 'estimated_charge_nanos' => $quote['estimated'],
                'reserved_nanos' => $quote['reserved'],
            ]);

            $user->increment('credit_reserved_nanos', $quote['reserved']);
            $this->entry($user->id, $generation->id, 'reserve:'.$generation->id, 'reserve', 0, $quote['reserved']);

            return [$generation, true];
        }, attempts: 3);

        if (! $created) {
            return $generation; 
        }
        try {
            $accepted = $this->api->submit($generation);
            if (empty($accepted['provider_id'])) {
                throw new RuntimeException('Provider response had no generation ID.');
            }
        } catch (RequestException $e) {
            if (in_array($e->response->status(), [400, 401, 403, 404, 409, 422, 429, 503], true)) {
                $this->fail($generation, 'Provider rejected request (HTTP '.$e->response->status().').');
            } else {
                $generation->update(['status' => 'uncertain', 'error_message' => 'Submission outcome unknown; no automatic retry.']);
            }
        } catch (\Throwable $e) {
            report($e);
            $generation->update(['status' => 'uncertain', 'error_message' => 'Submission outcome unknown; ask an admin to investigate.']);
        }

        if (isset($accepted)) {
            $generation->update(['provider_id' => $accepted['provider_id'], 'status' => 'queued']);
            try {
                $this->apply($generation, $accepted);
                $this->drainReceipts($accepted['provider_id']);
            } catch (\Throwable $e) {
                report($e); 
            }
        }

        return $generation->fresh();
    }

    public function fail(Generation $generation, string $message): void
    {
        $this->apply($generation, ['status' => 'failed', 'error' => $message]);
    }

    public function processReceipt(WebhookReceipt $receipt): void
    {
        if ($receipt->processed_at) {
            return;
        }
        $generation = Generation::query()->where('provider_id', $receipt->provider_id)->first();
        if (! $generation) {
            return; 
        }

        $snapshot = $this->api->normalize($receipt->payload);
        if ($snapshot['status'] === 'succeeded' && $snapshot['usage'] === null) {
            $snapshot = $this->api->retrieve($generation); 
        }
        $this->apply($generation, $snapshot);
        $receipt->update(['processed_at' => now()]);
    }

    public function drainReceipts(string $providerId): void
    {
        WebhookReceipt::query()->where('provider_id', $providerId)
            ->whereNull('processed_at')->orderBy('id')->get()
            ->each(fn (WebhookReceipt $receipt) => $this->processReceipt($receipt));
    }

    public function reconcile(Generation $generation): void
    {
        if (! $generation->provider_id || in_array($generation->status, ['succeeded', 'failed'], true)) {
            return;
        }
        $generation->update(['last_polled_at' => now()]);
        $this->apply($generation, $this->api->retrieve($generation));
    }

    public function apply(Generation $generation, array $snapshot): void
    {
        $status = $snapshot['status'];
        if (! in_array($status, ['queued', 'processing', 'succeeded', 'failed'], true)) {
            throw new RuntimeException('Invalid state.');
        }

        DB::transaction(function () use ($generation, $snapshot, $status) {
            $user = User::query()->lockForUpdate()->findOrFail($generation->user_id);
            $row = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            if (in_array($row->status, ['succeeded', 'failed'], true)) {
                return; 
            }
            if ($status === 'queued' || $status === 'processing') {
                if ($row->status !== 'processing') {
                    $row->update(['status' => $status]);
                }
                return;
            }

            $provider = 0;
            $charged = 0;
            if ($status === 'succeeded') {
                $usage = $snapshot['usage'] ?? null;
                if (! is_array($usage)) {
                    throw new RuntimeException('Success without authoritative token counts.');
                }
                if (config('pixelforge.driver') === 'live' && ! is_array($snapshot['rates'] ?? null)) {
                    throw new RuntimeException('Missing per-generation rates; refusing to guess bill.');
                }
                $cost = $this->pricing->charge($usage, $snapshot['rates'] ?? null);$cost = $this->pricing->charge($usage);
                $provider = $cost['provider'];
                $charged = $cost['customer'];
            }

            $user->decrement('credit_reserved_nanos', $row->reserved_nanos);
            $this->entry($user->id, $row->id, 'release:'.$row->id, 'release', 0, -$row->reserved_nanos);
            if ($charged > 0) {
                $user->decrement('credit_balance_nanos', $charged);
                $this->entry($user->id, $row->id, 'charge:'.$row->id, 'charge', -$charged, 0);
            }
            $url = $snapshot['image_url'] ?? null;
            $safeUrl = is_string($url) && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)
                ? $url : null;
            $row->update([
                'status' => $status, 'provider_cost_nanos' => $provider,
                'actual_charge_nanos' => $charged, 'usage' => $status === 'succeeded' ? $snapshot['usage'] : null,
                'image_url' => $status === 'succeeded' ? $safeUrl : null,
                'error_message' => $status === 'failed' ? substr((string) ($snapshot['error'] ?? 'Generation failed.'), 0, 400) : null,
                'completed_at' => isset($snapshot['completed_at'])
                    ? \Illuminate\Support\Carbon::parse($snapshot['completed_at'])->utc() : now('UTC'),
            ]);
        }, attempts: 3);
    }

    private function entry(int $userId, ?int $generationId, string $key, string $type, int $balance, int $reserved): void
    {
        CreditEntry::create([
            'user_id' => $userId, 'generation_id' => $generationId,
            'event_key' => $key, 'type' => $type,
            'balance_delta_nanos' => $balance, 'reserved_delta_nanos' => $reserved,
        ]);
    }
}
