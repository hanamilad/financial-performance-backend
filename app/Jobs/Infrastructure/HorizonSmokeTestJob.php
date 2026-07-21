<?php

namespace App\Jobs\Infrastructure;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Infrastructure smoke test: proves a job really travels through Redis and is
 * executed by a Horizon worker (FOUNDATION-005).
 *
 * It carries no business logic and no payload on purpose — writing a single
 * log line is the whole point, so a failure can only mean the queue path
 * itself is broken. Dispatch it by hand when verifying the stack:
 *
 *     docker compose exec app php artisan tinker
 *     >>> App\Jobs\Infrastructure\HorizonSmokeTestJob::dispatch();
 */
class HorizonSmokeTestJob implements ShouldQueue
{
    use Queueable;

    /**
     * The line written to the log when the job runs. Kept as a constant so the
     * test asserts the exact string the worker container prints.
     */
    public const string LOG_MESSAGE = 'Horizon smoke test job processed.';

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info(self::LOG_MESSAGE);
    }
}
