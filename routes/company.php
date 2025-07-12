<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\CompanyUserAuthController;
use App\Http\Controllers\Company\CompanyDeliveryManController;
use App\Http\Controllers\Company\CompanyDeliveryController;
use App\Http\Controllers\Company\CompanyCustomerController;
use App\Http\Controllers\Company\CompanyCustomerAddressController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Http\Controllers\Company\CompanyItemController;
use App\Http\Controllers\Company\CompanyNotificationController;
use App\Http\Controllers\Company\CompanyAddressController;

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

        // Customer management routes
        Route::get('customers', [CompanyCustomerController::class, 'index']);
        Route::get('customers/{id}', [CompanyCustomerController::class, 'show']);
        Route::post('customers', [CompanyCustomerController::class, 'store']);
        Route::put('customers/{id}', [CompanyCustomerController::class, 'update']);
        Route::delete('customers/{id}', [CompanyCustomerController::class, 'destroy']);

        // Customer addresses routes
        Route::get('customers/{id}/addresses', [CompanyCustomerAddressController::class, 'index']);
        Route::post('customers/{id}/addresses', [CompanyCustomerAddressController::class, 'store']);
        Route::put('customers/{customerId}/addresses/{addressId}', [CompanyCustomerAddressController::class, 'update']);
        Route::delete('customers/{customerId}/addresses/{addressId}', [CompanyCustomerAddressController::class, 'destroy']);

        // Item management routes
        Route::get('items', [CompanyItemController::class, 'index']);
        Route::post('items', [CompanyItemController::class, 'store']);
        Route::get('items/{id}', [CompanyItemController::class, 'show']);
        Route::put('items/{id}', [CompanyItemController::class, 'update']);
        Route::delete('items/{id}', [CompanyItemController::class, 'destroy']);

        // Company address management routes
        Route::get('addresses', [CompanyAddressController::class, 'index']);
        Route::post('addresses', [CompanyAddressController::class, 'store']);
        Route::get('addresses/{id}', [CompanyAddressController::class, 'show']);
        Route::put('addresses/{id}', [CompanyAddressController::class, 'update']);
        Route::delete('addresses/{id}', [CompanyAddressController::class, 'destroy']);
        Route::get('addresses/type/{type}', [CompanyAddressController::class, 'getByType']);

        // Push notification routes
        Route::post('notifications/device-token', [CompanyNotificationController::class, 'updateDeviceToken']);
        Route::post('notifications/test', [CompanyNotificationController::class, 'sendTestNotification']);
        Route::post('notifications/company', [CompanyNotificationController::class, 'sendToCompany']);
        Route::post('notifications/users', [CompanyNotificationController::class, 'sendToUsers']);
        Route::get('notifications/users', [CompanyNotificationController::class, 'getCompanyUsers']);

        // Dashboard
        Route::get('dashboard', [CompanyDashboardController::class, 'summary']);
    });
});