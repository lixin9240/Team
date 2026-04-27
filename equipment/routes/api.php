<?php

use App\Http\Controllers\LXController;
use App\Http\Controllers\LZWController;
use App\Http\Controllers\WLJController;
use Illuminate\Support\Facades\Route;


    // 公开接口
    Route::post('/auth/register', [LZWController::class, 'register']);//注册
    Route::post('/auth/login', [LZWController::class, 'login']);//登录（需要邮箱验证码）
    Route::post('/auth/forget-password', [LZWController::class, 'forgetPassword']);//忘记密码
    Route::post('/auth/send-email-code', [LZWController::class, 'sendEmailCode']);//发送邮箱验证码


    // 分类接口
    Route::group(['middleware' => 'jwt.auth', 'prefix' => 'categories'], function () {
        Route::get('/', [LXController::class, 'getCategories']);           // 获取分类列表（管理员和学生均可访问）
        Route::get('/statistics', [LXController::class, 'getCategoryStatistics']); // 分类统计
        Route::get('/{id}', [LXController::class, 'getCategory']);       // 获取分类详情
    });

    // 需要认证的接口
    Route::group(['middleware' => 'jwt.auth'], function () {
        // 分类接口已移到上面统一处理
    Route::get('/auth/me', [LZWController::class, 'me']);//获取当前用户信息
    Route::post('/auth/logout', [LZWController::class, 'logout']);//退出登录
    Route::put('/auth/profile', [LZWController::class, 'updateProfile']);//更新用户信息
    Route::post('/auth/avatar', [LZWController::class, 'uploadAvatar']);//上传头像
    // 管理员接口

            Route::get('/admin/users', [LZWController::class, 'adminUsers']);
        // 管理员注销用户
        Route::delete('/admin/users/{id}', [LXController::class, 'deactivateUser']);

    // =======================================
    // 设备分类模块（管理员接口）
    // =======================================
    Route::post('/admin/categories', [LXController::class, 'createCategory']);      // 创建分类
    Route::put('/admin/categories/{id}', [LXController::class, 'updateCategory']); // 更新分类
    Route::delete('/admin/categories/{id}', [LXController::class, 'deleteCategory']); // 删除分类
    Route::patch('/admin/categories/{id}/toggle-status', [LXController::class, 'toggleCategoryStatus']); // 切换分类启用/禁用状态


    // 设备大厅模块
    Route::get('/devices', [WLJController::class, 'getDevices']);//获取设备列表
    Route::get('/devices/{id}', [WLJController::class, 'getDevice']);//获取设备详情

    // 借用申请模块
    Route::post('/bookings', [WLJController::class, 'createBooking']);//创建借用申请

    // 我的借用记录模块
    Route::get('/bookings/my', [WLJController::class, 'getMyBookings']);//获取我的借用记录
    Route::patch('/bookings/{id}/return', [WLJController::class, 'returnBooking']);//归还设备

    // 注销账号
    Route::delete('/account', [WLJController::class, 'deleteAccount']);//注销账号
    // 获取待审核申请列表

    Route::get('/admin/bookings/pending', [LXController::class, 'getPendingBookings']);//获取待审核申请列表
    Route::get('/admin/bookings/rejected', [LXController::class, 'getRejectedBookings']);//获取拒绝借用申请列表
    Route::patch('/admin/bookings/{id}/audit', [LXController::class, 'auditBooking']);//审核借用申请
    Route::get('/admin/bookings/returning', [LXController::class, 'getReturningBookings']);//获取待审核归还列表
    Route::patch('/admin/bookings/{id}/return-audit', [LXController::class, 'auditReturnBooking']);//审核归还申请
    Route::get('/admin/bookings/returned', [LXController::class, 'getReturnedBookings']);//获取已归还列表
    Route::get('/admin/bookings/unreturned', [LXController::class, 'getUnreturnedBookings']);//获取未归还列表
    Route::get('/admin/bookings/return-rejected', [LXController::class, 'getReturnRejectedBookings']);//获取拒绝归还列表


    Route::post('/admin/devices', [LXController::class, 'createDevice']);//创建设备
    Route::put('/admin/devices/{id}', [LXController::class, 'updateDevice']);//更新设备信息
    Route::delete('/admin/devices/{id}', [LXController::class, 'deleteDevice']);//下架设备（软删除）

});

