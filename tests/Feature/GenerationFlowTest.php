<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\GenerationManager;
use App\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pixelforge.driver', 'fake');
        config()->set('pixelforge.webhook_secret', 'test-secret');
    }

    public function test_duplicate_and_out_of_order_events_cannot_charge_twice(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['credit_balance_nanos' => Money::fromUsd('10.00')])->save();
        $manager = app(GenerationManager::class);
        $request = [
            'client_request_id' => (string) Str::uuid(),
            'conversation_id' => null, 'prompt' => 'A modern architecture rendering',
            'size' => '1024x1024', 'quality' => 'medium', 'reference_urls' => [],
        ];
        $job = $manager->start($user, $request);
        $this->assertSame($job->id, $manager->start($user, $request)->id);
        $snapshot = [
            'status' => 'succeeded', 'image_url' => 'https://example.com/image.png',
            'usage' => ['input_text_tokens' => 10, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 200],
        ];
        $manager->apply($job, $snapshot);
        $manager->apply($job, $snapshot); // Duplicate successful webhook.
        $manager->apply($job, ['status' => 'processing']); // Out-of-order old status.

        $this->assertSame('succeeded', $job->fresh()->status);
        $this->assertSame(1, $user->creditEntries()->where('type', 'charge')->count());
        $this->assertSame(0, $user->fresh()->credit_reserved_nanos);
        $this->assertSame(
            Money::fromUsd('10.00') - $job->fresh()->actual_charge_nanos,
            $user->fresh()->credit_balance_nanos
        );
    }

    public function test_failed_generation_is_free_and_releases_hold(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['credit_balance_nanos' => Money::fromUsd('10.00')])->save();
        $job = app(GenerationManager::class)->start($user, [
            'client_request_id' => (string) Str::uuid(), 'conversation_id' => null,
            'prompt' => 'A forest [fail]', 'size' => '1024x1024', 'quality' => 'medium', 'reference_urls' => [],
        ]);
        app(GenerationManager::class)->apply($job, ['status' => 'failed', 'error' => 'Simulated failure']);
        $this->assertSame(0, $job->fresh()->actual_charge_nanos);
        $this->assertSame(0, $user->fresh()->credit_reserved_nanos);
        $this->assertSame(Money::fromUsd('10.00'), $user->fresh()->credit_balance_nanos);
    }

    public function test_user_cannot_open_someone_elses_chat_or_admin_report(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Private']);

        $this->actingAs($other)->get('/studio/'.$conversation->id)->assertNotFound();
        $this->actingAs($other)->get('/admin')->assertForbidden();
    }
}
