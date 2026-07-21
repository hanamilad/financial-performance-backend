<?php

use App\Jobs\Infrastructure\HorizonSmokeTestJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Horizon smoke test job
|--------------------------------------------------------------------------
|
| The job's own logic is tested here in isolation: handle() is called
| directly, with no queue and no worker involved.
|
| This proves nothing about Horizon. That a job really travels through Redis
| and is executed by the worker container is verified manually — dispatch it
| from tinker and watch it complete in the dashboard (README, "Queue worker").
|
*/

it('logs a single line when it runs', function () {
    Log::spy();

    (new HorizonSmokeTestJob)->handle();

    Log::shouldHaveReceived('info')
        ->once()
        ->with(HorizonSmokeTestJob::LOG_MESSAGE);
});

it('is a queued job', function () {
    expect(new HorizonSmokeTestJob)->toBeInstanceOf(ShouldQueue::class);
});
