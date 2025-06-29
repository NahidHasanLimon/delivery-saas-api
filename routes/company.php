<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\CompanyUserAuthController;
use App\Http\Controllers\Company\CompanyDeliveryManController;
use App\Http\Controllers\Company\CompanyDeliveryController;

Route::prefix('/')->group(function () {
    // Public
    Route::post('login',   [CompanyUserAuthController::class, 'login']);
    Route::post('refresh', [CompanyUserAuthController::class, 'refresh'])
         ->middleware('auth:company_user');
    
    // Protected
    Route::middleware('auth:company_user')->group(function () {
        Route::get('me',    [CompanyUserAuthController::class, 'me']);
        Route::post('logout', [CompanyUserAuthController::class, 'logout']);

        // Delivery man management
        Route::get('deliverymen', [CompanyDeliveryManController::class, 'index']);
        Route::post('deliverymen', [CompanyDeliveryManController::class, 'store']);
        Route::delete('deliverymen/{id}', [CompanyDeliveryManController::class, 'destroy']);

        // Delivery management
        Route::get('deliveries', [CompanyDeliveryController::class, 'index']);
        Route::post('deliveries', [CompanyDeliveryController::class, 'store']);
        Route::get('deliveries/{id}', [CompanyDeliveryController::class, 'show']);
        Route::patch('deliveries/{id}', [CompanyDeliveryController::class, 'update']);
    });
});