<?php

namespace App\Jobs\Infrastructure;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HorizonSmokeTestJob implements ShouldQueue
{
    use Queueable;

    public const string LOG_MESSAGE = 'Horizon smoke test job processed.';

    public function handle(): void
    {
        Log::info(self::LOG_MESSAGE);
    }
}
