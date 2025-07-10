<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\payments\PaymentController;
use App\Http\Controllers\payments\WalletController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\CheckAccessToken;
use App\Http\Middleware\CheckRefreshToken;

// Order Routes 
Route::middleware(CheckAccessToken::class)->prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'CreateOrder']);
    Route::prefix('negotiations')->group(function () {
        Route::post('/', [OrderController::class, 'CreateNegotiation']);
        Route::get('/{clientId}', [OrderController::class, 'GetNegotiationByClientId']);
    });
    Route::prefix('complaints')->group(function () {
        Route::post('/', [OrderController::class, 'CreateComplaint']);
        Route::get('/{clientId}', [OrderController::class, 'GetComplaintsByClientId']);
    });
    Route::prefix('reviews')->group(function () {
        Route::post('/', [OrderController::class, 'CreateReview']);
        Route::get('/{clientId}', [OrderController::class, 'GetReviewsByClientId']);
    });
});
// Chat Routes 
Route::middleware(CheckAccessToken::class)->prefix('chats')->group(function () {
    Route::post('/', [ChatController::class, 'SendChat']);
    Route::get('/{freelancerId}', [ChatController::class, 'GetChat']);
});

// Profile Routes
Route::middleware(CheckAccessToken::class)->prefix('profiles')->group(function () {
    Route::put('/update', [ProfileController::class, 'updateProfile']);
});

// Region Routes
Route::prefix('regions')->group(function () {
    Route::get('/provinces', [RegionController::class, 'GetAllProvinces']);
    Route::get('/regencies/{id}', [RegionController::class, 'GetRegenciesByProvinceId']);
    Route::get('/districts/{id}', [RegionController::class, 'GetDistrictsByRegencyId']);
    Route::get('/villages/{id}', [RegionController::class, 'GetVillagesByDistrictId']);
    Route::get('/domicile/{id}', [RegionController::class, 'GetDomicileByVillageId']);
});

// Auth Routes
Route::prefix('auths')->group(function () {
    Route::post('/SignUp', [AuthController::class, 'SignUp']);
    Route::post('/SignIn', [AuthController::class, 'SignIn']);
    Route::patch('/generate-refresh-token', [AuthController::class, 'GenerateRefreshToken'])->middleware(CheckRefreshToken::class);
    Route::post('/SignOut', [AuthController::class, 'SignOut'])->middleware(CheckAccessToken::class);
    Route::get('/email/verify/{id}', [VerificationController::class, 'verificationLink'])->name('verification.verify')->middleware('signed');
    Route::post('/signup', [AuthController::class, 'SignUp']);
    Route::post('/token/store', [AuthController::class, 'storeToken']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/forgot-password', [AuthController::class, 'ForgotPassword']);
    Route::get('/email/verify/{id}', [VerificationController::class, 'verificationLink'])
        ->name('verification.verify')
        ->middleware('signed');
    Route::get('/check-email', [AuthController::class, 'CheckEmail']);
    Route::post('/email/resend', [VerificationController::class, 'resendVerificationLink']);
    Route::patch('/complete-profile', [AuthController::class, 'CompleteProfile'])->middleware(CheckAccessToken::class);
    Route::patch('/generate-access-token', [AuthController::class, 'GenerateAccessToken'])->middleware(CheckAccessToken::class);
});
// payment routes
Route::middleware(CheckAccessToken::class)->prefix('payments')->group(function () {
    Route::post('/', [PaymentController::class, 'CreatePayment']);
    Route::post('/top-up', [PaymentController::class, 'CreateTopUp']);
    Route::prefix('wallets')->group(function () {
        Route::post('/', [WalletController::class, 'CreateWallet']);
        Route::get('/', [WalletController::class, 'CreateTopUp']);
    });
});

Route::post('/payments/callback', [PaymentController::class, 'Callback']);
