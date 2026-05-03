<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LXController;
use App\Http\Controllers\WLJController;

Route::post('/register', [LXController::class, 'register']);//注册接口
Route::post('/login', [LXController::class, 'login']);//登录接口
Route::post('/send-verification-code', [LXController::class, 'sendVerificationCode']);//发送验证码接口

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [LXController::class, 'logout']);//注销接口
    Route::get('/me', [LXController::class, 'me']);//获取用户信息接口
    
    // 商品批量导入接口
    Route::post('/products/import', [LXController::class, 'importProducts']);//批量导入商品
    
    // 商品列表查询接口
    Route::get('/products', [WLJController::class, 'index']);//获取商品列表
    
    // 商品详情接口
    Route::get('/products/{id}', [WLJController::class, 'show']);//获取商品详情
    
    // 提交预订订单接口
    Route::post('/orders', [WLJController::class, 'createOrder']);//提交预订单
    
    // 订单报表导出接口
    Route::get('/orders/export', [LXController::class, 'exportOrders']);//导出订单Excel
    
    // 订单审核接口（管理员）
    Route::put('/admin/orders/{id}/review', [LXController::class, 'reviewOrder']);//审核订单
});
