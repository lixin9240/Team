<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LXController;
use App\Http\Controllers\WLJController;

Route::post('/register', [LXController::class, 'register']);//注册接口
Route::post('/login', [LXController::class, 'login']);//登录接口
Route::post('/send-verification-code', [LXController::class, 'sendVerificationCode']);//发送验证码接口

Route::get('/products', [WLJController::class, 'index']);
Route::get('/products/{id}', [WLJController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [LXController::class, 'logout']);
    Route::get('/me', [LXController::class, 'me']);
    Route::post('/orders', [WLJController::class, 'store']);
});
