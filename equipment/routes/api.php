<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\EquipmentController;
use Illuminate\Support\Facades\Route;

// =========================================
// 公开接口（无需认证）
// =========================================
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forget-password', [AuthController::class, 'forgetPassword']);
Route::post('/auth/send-email-code', [AuthController::class, 'sendEmailCode']);

// =========================================
// 需要认证的接口
// =========================================
Route::group(['middleware' => 'jwt.auth'], function () {

    // --- 认证相关 ---
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);
    Route::get('/admin/users', [AuthController::class, 'adminUsers']);
    Route::delete('/account', [AuthController::class, 'deleteAccount']);

    // --- 设备大厅 ---
    Route::get('/devices', [EquipmentController::class, 'getDevices']);
    Route::get('/devices/{id}', [EquipmentController::class, 'getDevice']);

    // --- 借用申请 ---
    Route::post('/bookings', [EquipmentController::class, 'createBooking']);
    Route::get('/bookings/my', [EquipmentController::class, 'getMyBookings']);
    Route::patch('/bookings/{id}/return', [EquipmentController::class, 'returnBooking']);

    // --- 分类（公开查看）---
    Route::get('/categories', [AdminController::class, 'getCategories']);
    Route::get('/categories/statistics', [AdminController::class, 'getCategoryStatistics']);
    Route::get('/categories/{id}', [AdminController::class, 'getCategory']);

    // --- 管理员接口 ---
    // 借用审核
    Route::get('/admin/bookings/pending', [AdminController::class, 'getPendingBookings']);
    Route::get('/admin/bookings/rejected', [AdminController::class, 'getRejectedBookings']);
    Route::patch('/admin/bookings/{id}/audit', [AdminController::class, 'auditBooking']);
    Route::get('/admin/bookings/returning', [AdminController::class, 'getReturningBookings']);
    Route::patch('/admin/bookings/{id}/return-audit', [AdminController::class, 'auditReturnBooking']);
    Route::get('/admin/bookings/returned', [AdminController::class, 'getReturnedBookings']);
    Route::get('/admin/bookings/unreturned', [AdminController::class, 'getUnreturnedBookings']);
    Route::get('/admin/bookings/return-rejected', [AdminController::class, 'getReturnRejectedBookings']);

    // 设备管理
    Route::post('/admin/devices', [AdminController::class, 'createDevice']);
    Route::put('/admin/devices/{id}', [AdminController::class, 'updateDevice']);
    Route::delete('/admin/devices/{id}', [AdminController::class, 'deleteDevice']);

    // 分类管理
    Route::post('/admin/categories', [AdminController::class, 'createCategory']);
    Route::put('/admin/categories/{id}', [AdminController::class, 'updateCategory']);
    Route::delete('/admin/categories/{id}', [AdminController::class, 'deleteCategory']);
    Route::patch('/admin/categories/{id}/toggle-status', [AdminController::class, 'toggleCategoryStatus']);

    // 用户管理
    Route::delete('/admin/users/{id}', [AdminController::class, 'deactivateUser']);
});
