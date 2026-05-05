<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LXController;
use App\Http\Controllers\WLJController;
use App\Http\Controllers\LZWController;

// OSS配置测试路由（无需认证）
Route::get('/test-oss-config', function () {
    return response()->json([
        'bucket' => config('filesystems.disks.oss.bucket'),
        'endpoint' => config('filesystems.disks.oss.endpoint'),
        'access_id' => substr(config('filesystems.disks.oss.access_id'), 0, 10) . '...',
        'has_access_key' => !empty(config('filesystems.disks.oss.access_key')),
    ]);
});

// OSS连接测试路由（无需认证）
Route::get('/test-oss-connection', function () {
    try {
        $ossService = app(\App\Services\OssService::class);
        
        // 尝试获取一个测试文件的URL来验证连接
        $testUrl = $ossService->url('test.txt');
        
        return response()->json([
            'status' => 'success',
            'message' => 'OSS服务初始化成功',
            'test_url' => $testUrl,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_code' => $e->getCode(),
        ], 500);
    }
});

// OSS完整配置调试路由（无需认证）
Route::get('/debug-oss', function () {
    $config = config('filesystems.disks.oss');
    
    return response()->json([
        'driver' => $config['driver'] ?? null,
        'access_id' => isset($config['access_id']) ? substr($config['access_id'], 0, 10) . '...' : null,
        'access_key' => isset($config['access_key']) ? '已设置(长度:' . strlen($config['access_key']) . ')' : null,
        'bucket' => $config['bucket'] ?? null,
        'endpoint' => $config['endpoint'] ?? null,
        'cdn_domain' => $config['cdn_domain'] ?? null,
        'ssl' => $config['ssl'] ?? null,
        'is_cname' => $config['is_cname'] ?? null,
        'debug' => $config['debug'] ?? null,
    ]);
});

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
    
    // 我的订单接口
    Route::get('/my-orders', [LXController::class, 'myOrders']);//查看我的订单
    
    // 商品维护接口（管理员）
    Route::put('/products/{id}', [LXController::class, 'updateProduct']);//修改商品信息
    
    // 数据看板接口（管理员）
    Route::get('/admin/stats', [LXController::class, 'adminStats']);//系统统计数据

        // 上传定制稿
    Route::post('/orders/{id}/design', [LZWController::class, 'uploadDesign']);//上传设计稿


    // 确认收货/核销
    Route::post('/orders/{id}/complete', [LZWController::class, 'completeOrder']);//完成订单
});
