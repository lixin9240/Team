<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Support\Result;
use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\TraceIdMiddleware::class,
            \App\Http\Middleware\ApiLogMiddleware::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {

            // 参数验证异常
            if ($e instanceof ValidationException) {
                return Result::error(
                    ResponseCode::PARAM_ERROR,
                    collect($e->errors())->flatten()->first()
                );
            }

            // 未登录
            if ($e instanceof AuthenticationException) {
                return Result::error(ResponseCode::UNAUTHORIZED);
            }

            // 模型不存在
            if ($e instanceof ModelNotFoundException) {
                return Result::error(ResponseCode::DATA_NOT_FOUND);
            }

            // 路由不存在
            if ($e instanceof NotFoundHttpException) {
                return Result::error(
                    ResponseCode::DATA_NOT_FOUND,
                    '接口不存在'
                );
            }

            // 业务异常
            if ($e instanceof BusinessException) {
                return Result::error(
                    $e->codeEnum,
                    $e->getMessage()
                );
            }

            // 数据库异常
            if ($e instanceof QueryException) {
                Log::channel('exception')->error(
                    '数据库异常',
                    [
                        'trace_id' => $request->attributes->get('trace_id'),
                        'sql' => $e->getSql(),
                        'bindings' => $e->getBindings(),
                        'message' => $e->getMessage(),
                    ]
                );

                return Result::error(ResponseCode::DATABASE_ERROR);
            }

            // 系统异常日志
            Log::channel('exception')->error(
                $e->getMessage(),
                [
                    'trace_id' => $request->attributes->get('trace_id'),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            // 未知异常兜底
            return Result::error(ResponseCode::SYSTEM_ERROR);
        });
    })->create();
