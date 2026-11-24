<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class PixelforgeApi
{
    public function ready(): bool
    {
        return config('pixelforge.driver') === 'fake'
            || (config('pixelforge.contract_verified') && filled(config('pixelforge.api_key')));
    }

    private function live(): void
    {
        if (! config('pixelforge.contract_verified') || ! config('pixelforge.api_key')) {
            throw new RuntimeException('Live Pixelforge API has not been configured.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $this->live();

        return Http::baseUrl(rtrim(config('pixelforge.base_url'), '/'))
            ->withToken(config('pixelforge.api_key'))
            ->acceptJson()->connectTimeout(4)->timeout(18);
    }

    public function pricing(): array
    {
        if (config('pixelforge.driver') === 'fake') {
            return ['rates' => config('pixelforge.rates'), 'tokens' => config('pixelforge.tokens')];
        }

        return Cache::remember('pixelforge.public-pricing', 300, function () {
            $data = Http::acceptJson()->timeout(10)
                ->get(rtrim(config('pixelforge.base_url'), '/').'/v1/pricing')->throw()->json();

            if (! is_array($data) || ! isset($data['rates'], $data['tokens'])) {
                throw new RuntimeException('Invalid Pixelforge pricing response.');
            }

            return $data;
        });
    }

    public function submit(Generation $generation): array
    {
        if (config('pixelforge.driver') === 'fake') {
            return ['provider_id' => 'fake-'.Str::uuid(), 'status' => 'queued'];
        }

        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => $generation->client_request_id])
            ->post('/v1/generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_urls ?? [],
                'metadata' => ['client_request_id' => $generation->client_request_id],
            ])->throw();

        return $this->normalize($response->json());
    }

    public function retrieve(Generation $generation): array
    {
        if (config('pixelforge.driver') === 'fake') {
            $seconds = max(0, time() - $generation->created_at->timestamp);
            $slow = str_contains($generation->prompt, '[slow]');
            $status = $seconds < ($slow ? 45 : 7) ? 'processing'
                : (str_contains($generation->prompt, '[fail]') ? 'failed' : 'succeeded');

            return [
                'provider_id' => $generation->provider_id,
                'status' => $status,
                'usage' => [
                    'input_text_tokens' => (int) ceil(mb_strlen($generation->prompt) / 4),
                    'input_image_tokens' => count($generation->reference_urls ?? []) * 765,
                    'output_tokens' => config("pixelforge.tokens.output.{$generation->quality}.{$generation->size}"),
                    'reasoning_tokens' => config("pixelforge.tokens.reasoning.{$generation->quality}.min"),
                ],
                'rates' => config('pixelforge.rates'),
                'image_url' => null,
                'error' => $status === 'failed' ? 'Simulated provider failure' : null,
                'completed_at' => in_array($status, ['succeeded', 'failed'], true) ? now('UTC')->toIso8601String() : null,
            ];
        }

        return $this->normalize(
            $this->request()->get('/v1/generations/'.rawurlencode($generation->provider_id))->throw()->json()
        );
    }

    public function normalize(array $response): array
    {
        $data = $response['data']['object'] ?? $response;
        if (! is_array($data)) {
            throw new RuntimeException('Missing Pixelforge generation.');
        }

        $status = $data['status'] ?? null;
        if (! in_array($status, ['queued', 'processing', 'succeeded', 'failed'], true)) {
            throw new RuntimeException('Invalid Pixelforge status.');
        }
        $usage = $data['usage'] ?? null;
        if ($usage !== null && ! is_array($usage)) {
            throw new RuntimeException('Invalid Pixelforge usage.');
        }

        return [
            'provider_id' => $data['id'] ?? null,
            'status' => $status,
            'usage' => $usage,
            'rates' => $data['rates'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'error' => is_array($data['error'] ?? null)
                ? ($data['error']['message'] ?? 'Generation failed.') : ($data['error'] ?? null),
            'completed_at' => $data['completed_at'] ?? null,
        ];
    }

    public function verifyWebhook(string $raw, ?string $signature): bool
    {
        $secret = (string) config('pixelforge.webhook_secret');
        if ($secret === '' || ! is_string($signature)) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[trim($key)] = trim($value);
        }
        // LOCAL EXAMPLE ONLY: replace this if docs sign a timestamp or use another header.
        $timestamp = $parts['t'] ?? '';
        $digest = $parts['v1'] ?? '';
        if (! ctype_digit($timestamp) || ! preg_match('/\A[0-9a-f]{64}\z/', $digest)
            || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$raw, $secret), $digest);
    }

    public function usage(): array
    {
        if (config('pixelforge.driver') === 'fake') {
            return ['driver' => 'fake', 'note' => 'No external API usage in demo mode.'];
        }

        // Endpoint is explicitly mentioned in the assessment; shape requires /docs.
        return $this->request()->get('/v1/usage')->throw()->json();
    }
}
