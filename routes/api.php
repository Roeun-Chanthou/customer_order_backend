<?php

use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\OrderItemApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('/products', ProductApiController::class);
Route::get('/products/{pid}/image', [ProductApiController::class, 'getImage']);


Route::post('/customer/auth', [CustomerApiController::class, 'auth']);
Route::post('/customer/verify', [CustomerApiController::class, 'verify']);
Route::post('/customer/setup', [CustomerApiController::class, 'setup']);
Route::get('/customer/show', [CustomerApiController::class, 'show']);
Route::post('/customer/photo', [CustomerApiController::class, 'updatePhoto']);
Route::post('/customer/login', [CustomerApiController::class, 'login']);

Route::post('/customer/request-reset-password', [CustomerApiController::class, 'requestResetPassword']);
Route::post('/customer/resend-otp', [CustomerApiController::class, 'resendOtp']);
Route::post('/customer/verify-reset-otp', [CustomerApiController::class, 'verifyResetOtp']);
Route::post('/customer/reset-password', [CustomerApiController::class, 'resetPassword']);
Route::post('/test-email', [CustomerApiController::class, 'testEmail']);


Route::post('/orders/place', [OrderApiController::class, 'placeOrder']);
Route::get('/orders', [OrderApiController::class, 'index']);
Route::get('/orders/customer/{customer_id}', [OrderApiController::class, 'listByCustomer']);
Route::get('/orders/{oid}', [OrderApiController::class, 'show']);


Route::get('orders/{order_id}/items', [OrderItemApiController::class, 'index']);
Route::post('orders/{order_id}/items', [OrderItemApiController::class, 'store']);
Route::put('orders/{order_id}/items/{item_id}', [OrderItemApiController::class, 'update']);
Route::delete('orders/{order_id}/items/{item_id}', [OrderItemApiController::class, 'destroy']);
