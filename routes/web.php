<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/login/otp', [LoginController::class, 'showOtp'])->name('otp.show');
    Route::post('/login/otp/verify', [LoginController::class, 'verifyOtp'])->middleware('throttle:two-factor')->name('otp.verify');
    Route::post('/login/otp/resend', [LoginController::class, 'resendOtp'])->middleware('throttle:two-factor')->name('otp.resend');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Admin-created accounts are passwordless until activated here (signed, single-use, 72h).
Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('/onboarding/{user}', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/{user}', [OnboardingController::class, 'store'])->middleware('throttle:login')->name('onboarding.store');
});

Route::get('/dashboard', Dashboard::class)->middleware('auth')->name('dashboard');
