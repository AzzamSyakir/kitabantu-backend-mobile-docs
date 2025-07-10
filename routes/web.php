<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-email-view', function () {
    return view('emails.verify', [
        'url' => 'https://example.com/verify',
        'user' => (object) ['name' => 'John Doe'],
    ]);
});

Route::prefix('auths')->controller(AuthController::class)->group(function () {
    Route::get('/google/redirect', 'RedirectGoogle');
    Route::get('/google/callback', 'callBackGoogle');
});