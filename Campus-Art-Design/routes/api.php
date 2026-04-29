<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LXController;

Route::post('/register', [LXController::class, 'register']);//注册接口
Route::post('/login', [LXController::class, 'login']);//登录接口
Route::post('/send-verification-code', [LXController::class, 'sendVerificationCode']);//发送验证码接口

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [LXController::class, 'logout']);//注销接口
    Route::get('/me', [LXController::class, 'me']);//获取用户信息接口
});
