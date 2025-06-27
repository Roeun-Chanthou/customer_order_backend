<?php

use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\CustomerApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('/products', ProductApiController::class);
Route::get('/products/{pid}/image', [ProductApiController::class, 'getImage']);


Route::post('/customer/auth', [CustomerApiController::class, 'auth']);         // Email & password login/register, send OTP
Route::post('/customer/verify', [CustomerApiController::class, 'verify']);     // Verify OTP
Route::post('/customer/setup', [CustomerApiController::class, 'setup']);       // Fill customer info
Route::get('/customer/show', [CustomerApiController::class, 'show']);          // Show profile by email (?email=...)
Route::post('/customer/photo', [CustomerApiController::class, 'updatePhoto']); // Update only profile photo
Route::post('/customer/login', [CustomerApiController::class, 'login']);
Route::post('/customer/request-reset-password', [CustomerApiController::class, 'requestResetPassword']);
Route::post('/customer/verify-reset-otp', [CustomerApiController::class, 'verifyResetOtp']);
Route::post('/customer/reset-password', [CustomerApiController::class, 'resetPassword']);
Route::post('/test-email', [CustomerApiController::class, 'testEmail']);

Route::post('/wishlist/add', [WishlistController::class, 'addToWishlist']);
Route::post('/orders/place', [OrderController::class, 'placeOrder']);

