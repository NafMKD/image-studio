<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Generation;
use App\Services\GenerationManager;
use Throwable;

#[Signature('app:reconcile-generations')]
#[Description('Command description')]
class ReconcileGenerations extends Command
{
    protected $signature = 'pixelforge:reconcile';
    protected $description = 'Poll outstanding generations when a webhook is delayed or missing';
    
    /**
     * Execute the console command.
     */
    public function handle(GenerationManager $manager): int
    {
        Generation::query()->whereIn('status', ['submitting', 'queued', 'processing'])
            ->whereNotNull('provider_id')
            ->where(fn ($q) => $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<', now()->subSeconds(40)))
            ->orderBy('id')->limit(80)->get()->each(function (Generation $generation) use ($manager) {
                try {
                    $manager->reconcile($generation);
                } catch (Throwable $e) {
                    report($e); // The next cron run can retry a safe GET.
                }
            });

        return self::SUCCESS;
    }
}
