<?php

namespace App\Services;

use DomainException;

final class Pricing
{
    /** Costs are integer nanodollars; never use floating-point dollars. */
    public function charge(array $usage, ?array $rates = null): array
     {
        $rates ??= config('pixelforge.rates'); // Fake tests only; live passes per-generation rates.
        $numerator = 0;
        foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'] as $category) {
            $tokens = $usage[$category] ?? null;
             if (! is_int($tokens) || $tokens < 0 || $tokens > 10_000_000 || ! isset($rates[$category])) {
                throw new DomainException("Invalid token usage/rate: {$category}.");
             }
            $numerator += $tokens * Money::fromUsd((string) $rates[$category]);
        }

        return [
            'provider' => intdiv($numerator + 500_000, 1_000_000),
            'customer' => intdiv($numerator * 5 + 2_000_000, 4_000_000),
        ];
    }

    public function quote(int $characters, string $size, string $quality, int $references): array
    {
        $pricing = app(PixelforgeApi::class)->pricing(); 
        $rules = $pricing['tokens'];
        $output = $rules['output'][$quality][$size] ?? null;
        $reasoning = $rules['reasoning'][$quality] ?? null;
        if (! is_int($output) || ! is_array($reasoning) || $characters < 0 || $references < 0) {
            throw new DomainException('Unsupported generation options.');
        }

        $text = (int) ceil($characters / (int) $rules['characters_per_text_token']);
        $textMax = (int) ceil($text * (1 + (float) $rules['text_token_variance']));
        $image = $references * (int) $rules['per_reference_image'];

        $estimate = $this->charge([
            'input_text_tokens' => $text,
            'input_image_tokens' => $image,
            'output_tokens' => $output,
            'reasoning_tokens' => intdiv($reasoning['min'] + $reasoning['max'], 2),
        ], $pricing['rates']);
        $maximum = $this->charge([
            'input_text_tokens' => $textMax,
            'input_image_tokens' => $image,
            'output_tokens' => $output,
            'reasoning_tokens' => $reasoning['max'],
        ], $pricing['rates']);

        return [
            'estimated' => $estimate['customer'],
            'reserved' => max($estimate['customer'], $maximum['customer']),
        ];
    }
}
