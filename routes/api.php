<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| are stateless, use the "api" middleware group, and are typically
| prefixed with "api/".
|
*/

Route::middleware('api')->group(function () {
    // Company-user auth
    Route::prefix('company')->group(base_path('routes/company.php'));

    // // Admin-panel auth
    // Route::prefix('admin')->group(base_path('routes/admin.php'));

    // // Delivery-man auth
    // Route::prefix('deliveryman')->group(base_path('routes/deliveryman.php'));
});