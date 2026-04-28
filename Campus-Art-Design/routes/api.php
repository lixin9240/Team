<?php

use Illuminate\Support\Facades\Route;

Route::post('login', [App\Http\Controllers\LXController::class, 'login']);
Route::post('register', [App\Http\Controllers\LXController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    Route::post('logout', [App\Http\Controllers\LXController::class, 'logout']);
    Route::post('refresh', [App\Http\Controllers\LXController::class, 'refresh']);
    Route::get('me', [App\Http\Controllers\LXController::class, 'me']);
});