<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 配置认证中间件，API认证失败时返回JSON而不是重定向
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->redirectUsersTo(fn () => '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
