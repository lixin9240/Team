<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-oss-config', function () {
    return response()->json([
        'bucket' => config('filesystems.disks.oss.bucket'),
        'endpoint' => config('filesystems.disks.oss.endpoint'),
        'access_id' => substr(config('filesystems.disks.oss.access_id'), 0, 5) . '...',
        'has_access_key' => !empty(config('filesystems.disks.oss.access_key')),
    ]);
});

