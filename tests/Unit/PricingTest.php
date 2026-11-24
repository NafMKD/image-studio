<?php

namespace Tests\Unit;

use App\Services\Money;
use App\Services\Pricing;
use Tests\TestCase;

class PricingTest extends TestCase
{
    public function test_all_money_is_integer_and_customer_markup_is_exact(): void
    {
        config()->set('pixelforge.rates', [
            'input_text_tokens' => '1.00',
            'input_image_tokens' => '2.00',
            'output_tokens' => '4.00', 'reasoning_tokens' => '0.00',
        ]);

        $cost = (new Pricing)->charge([
            'input_text_tokens' => 1_000_000,
            'input_image_tokens' => 500_000,
            'output_tokens' => 250_000, 'reasoning_tokens' => 0,
        ]);
        // 1.00 + 1.00 + 1.00 = $3.00; 25% markup => $3.75.
        $this->assertSame(Money::fromUsd('3.00'), $cost['provider']);
        $this->assertSame(Money::fromUsd('3.75'), $cost['customer']);
    }

    public function test_fractional_cent_is_preserved(): void
    {
        config()->set('pixelforge.rates', [
            'input_text_tokens' => '0.10',
            'input_image_tokens' => '0.00',
            'output_tokens' => '0.00', 'reasoning_tokens' => '0.00',
        ]);
        $cost = (new Pricing)->charge([
            'input_text_tokens' => 1,
            'input_image_tokens' => 0,
            'output_tokens' => 0, 'reasoning_tokens' => 0,
        ]);
        $this->assertSame(100, $cost['provider']); // $0.000000100
        $this->assertSame(125, $cost['customer']); // $0.000000125
        $this->assertSame('$0.000000125', Money::display($cost['customer']));
    }
}
