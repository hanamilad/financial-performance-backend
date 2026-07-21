<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Readiness endpoint: reports whether the application can reach the backing
 * services it needs to serve real traffic. Liveness stays on GET /up.
 *
 * The response body is deliberately limited to {status, services}: no
 * exception text, driver detail, host, port, database name, credential,
 * SQLSTATE, stack trace, timing or version is ever disclosed, because this
 * endpoint is unauthenticated at this stage (FOUNDATION-004, DEC-039).
 */
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

    /**
     * Probe MySQL over the dedicated short-timeout health connection so the
     * application's own pooling and timeouts stay untouched (DEC-040).
     */
    private function checkDatabase(): bool
    {
        try {
            DB::connection('health_mysql')->selectOne('SELECT 1 AS ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Probe Redis over the dedicated short-timeout health connection, never
     * the default/cache connections (DEC-040).
     */
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
