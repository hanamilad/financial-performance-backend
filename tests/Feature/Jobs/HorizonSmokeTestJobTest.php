<?php

use App\Jobs\Infrastructure\HorizonSmokeTestJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

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
