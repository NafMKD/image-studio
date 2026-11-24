<?php

namespace App\Services;

use App\Models\Generation;
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
            throw new RuntimeException('Live Pixelforge contract and API key must be verified/configured first.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $this->live();

        return Http::baseUrl(rtrim(config('pixelforge.base_url'), '/'))
            ->withToken(config('pixelforge.api_key'))
            ->acceptJson()->connectTimeout(4)->timeout(18);
    }

    public function submit(Generation $generation): array
    {
        if (config('pixelforge.driver') === 'fake') {
            return ['provider_id' => 'fake-'.Str::uuid(), 'status' => 'queued'];
        }

        $body = [
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_image_urls' => $generation->reference_urls ?? [],
        ];
        $response = $this->request()->post('/v1/generations', $body)->throw();

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
                    'prompt_tokens' => (int) ceil(mb_strlen($generation->prompt) / 4),
                    'reference_tokens' => count($generation->reference_urls ?? []) * 250,
                    'output_tokens' => config("pixelforge.output_tokens_estimate.{$generation->quality}.{$generation->size}"),
                ],
                'image_url' => null, 'error' => $status === 'failed' ? 'Simulated provider failure' : null,
            ];
        }

        $response = $this->request()->get('/v1/generations/'.rawurlencode($generation->provider_id))->throw();

        return $this->normalize($response->json());
    }

    public function normalize(array $response): array
    {
        $data = $response['data'] ?? $response;
        $rawStatus = strtolower((string) ($data['status'] ?? ''));
        $status = match ($rawStatus) {
            'queued', 'pending' => 'queued',
            'processing', 'running' => 'processing',
            'succeeded', 'completed', 'success' => 'succeeded',
            'failed', 'error' => 'failed',
            default => throw new RuntimeException('Unknown provider status: '.$rawStatus),
        };
        $usage = $data['usage'] ?? null;
        if ($usage !== null && ! is_array($usage)) {
            throw new RuntimeException('Invalid provider token usage.');
        }

        return [
            'provider_id' => (string) ($data['generation_id'] ?? $data['id'] ?? ''),
            'status' => $status,
            'usage' => $usage === null ? null : [
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'reference_tokens' => $usage['reference_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
            ],
            'image_url' => $data['image_url'] ?? null,
            'error' => $data['error'] ?? null,
        ];
    }

    public function verifyWebhook(string $raw, ?string $signature): bool
    {
        $secret = (string) config('pixelforge.webhook_secret');
        if ($secret === '' || $signature === null) {
            return false;
        }
        if (config('pixelforge.driver') !== 'fake') {
            $this->live(); // Real signature scheme MUST be verified from /docs first.
        }
        // LOCAL EXAMPLE ONLY: replace this if docs sign a timestamp or use another header.
        $expected = hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $signature);
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
