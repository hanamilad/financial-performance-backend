<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $isDatabaseHealthy = $this->checkDatabase();
        $isRedisHealthy = $this->checkRedis();
        $isHealthy = $isDatabaseHealthy && $isRedisHealthy;

        return response()
            ->json([
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'services' => [
                    'database' => $isDatabaseHealthy ? 'healthy' : 'unhealthy',
                    'redis' => $isRedisHealthy ? 'healthy' : 'unhealthy',
                ],
            ], $isHealthy ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection('health_mysql')->selectOne('SELECT 1 AS ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection('health_redis')->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
