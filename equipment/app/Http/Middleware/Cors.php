<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 设置 CORS 响应头
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept',
            'Access-Control-Expose-Headers' => 'Authorization',
            'Access-Control-Max-Age' => '86400',
        ];

        // 处理预检请求（OPTIONS）
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, $headers);
        }

        $response = $next($request);

        // 添加 CORS 头到响应
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}