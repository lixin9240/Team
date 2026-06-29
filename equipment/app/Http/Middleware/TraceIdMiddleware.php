<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TraceIdMiddleware
{
    /**
     * 为每个请求生成唯一TraceId
     */
    public function handle($request, Closure $next)
    {
        $traceId = (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);

        Log::withContext([
            'trace_id' => $traceId,
        ]);

        return $next($request);
    }
}
