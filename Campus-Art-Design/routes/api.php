<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LXController;

Route::post('/register', [LXController::class, 'register']);
Route::post('/login', [LXController::class, 'login']);
Route::post('/send-verification-code', [LXController::class, 'sendVerificationCode']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [LXController::class, 'logout']);
    Route::get('/me', [LXController::class, 'me']);
});
