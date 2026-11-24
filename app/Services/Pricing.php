<?php

namespace App\Services;

use DomainException;

final class Pricing
{
    /** Exact arithmetic, with one final half-up rounding to one nanodollar. */
    public function charge(array $usage): array
    {
        $numerator = 0; // Nanodollars multiplied by 1,000,000 (token-rate denominator).

        foreach (config('pixelforge.rates') as $category => $usdPerMillion) {
            $tokens = $usage[$category] ?? null;
            if (! is_int($tokens) || $tokens < 0 || $tokens > 10_000_000) {
                throw new DomainException("Invalid token count for {$category}.");
            }
            $numerator += $tokens * Money::fromUsd((string) $usdPerMillion);
        }

        // Provider = sum(tokens * USD-per-million) / 1,000,000.
        $provider = intdiv($numerator + 500_000, 1_000_000);
        // User = unrounded provider cost * 5/4 (25% markup).
        $customer = intdiv($numerator * 5 + 2_000_000, 4_000_000);

        return ['provider' => $provider, 'customer' => $customer];
    }

    public function quote(int $characters, string $size, string $quality, int $references): array
    {
        $estimates = config('pixelforge.output_tokens_estimate');
        $maximums = config('pixelforge.output_tokens_max');
        if (! isset($estimates[$quality][$size], $maximums[$quality][$size])) {
            throw new DomainException('Unsupported size or quality.');
        }

        $estimate = $this->charge([
            'prompt_tokens' => (int) ceil($characters / 4),
            'reference_tokens' => $references * (int) config('pixelforge.reference_tokens_estimate'),
            'output_tokens' => $estimates[$quality][$size],
        ]);
        $ceiling = $this->charge([
            'prompt_tokens' => $characters * 4, 
            'reference_tokens' => $references * (int) config('pixelforge.reference_tokens_max'),
            'output_tokens' => $maximums[$quality][$size],
        ]);

        return [
            'estimated' => $estimate['customer'],
            'reserved' => max($estimate['customer'], $ceiling['customer']),
        ];
    }
}
