<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class ApiLogMiddleware
{
    /**
     * 记录所有 HTTP API 请求（参数、响应、耗时）
     */
    public function handle($request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('api')->info('API请求记录', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
            'request' => $request->except(['password', 'password_confirmation', 'token']),
            'response_code' => $response->status(),
            'duration_ms' => $duration,
        ]);

        return $response;
    }
}
