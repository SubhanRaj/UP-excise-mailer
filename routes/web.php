<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DesignationController;
use App\Http\Controllers\Admin\MailAccountController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\MailTemplateController;
use App\Livewire\CampaignBuilder;
use App\Livewire\TestEmailSender;
use App\Http\Controllers\RecipientController;
use App\Http\Controllers\RecipientListController;
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

Route::get('/recipients', [RecipientController::class, 'index'])->middleware('auth')->name('recipients.index');

Route::prefix('recipients')->name('recipients.')->middleware(['auth', 'is_admin', 'throttle:mutations'])->group(function () {
    Route::get('/zones/{zone}/edit', [RecipientController::class, 'editZone'])->name('zones.edit');
    Route::put('/zones/{zone}', [RecipientController::class, 'updateZone'])->name('zones.update');
    Route::get('/divisions/{division}/edit', [RecipientController::class, 'editDivision'])->name('divisions.edit');
    Route::put('/divisions/{division}', [RecipientController::class, 'updateDivision'])->name('divisions.update');
    Route::get('/districts/{district}/edit', [RecipientController::class, 'editDistrict'])->name('districts.edit');
    Route::put('/districts/{district}', [RecipientController::class, 'updateDistrict'])->name('districts.update');
    Route::get('/template/{level}', [RecipientController::class, 'downloadTemplate'])->name('template');
    Route::get('/import', \App\Livewire\OfficerDirectoryImportWizard::class)->name('import');
});

Route::get('/templates', [MailTemplateController::class, 'index'])->middleware('auth')->name('templates.index');
Route::resource('templates', MailTemplateController::class)->only(['create', 'store', 'edit', 'update', 'destroy'])
    ->middleware(['auth', 'privilege:templates.manage', 'throttle:mutations']);

Route::get('/recipient-lists', [RecipientListController::class, 'index'])->middleware('auth')->name('recipient-lists.index');
Route::get('/recipient-lists/{recipientList}', [RecipientListController::class, 'show'])->middleware('auth')->name('recipient-lists.show');
Route::middleware(['auth', 'privilege:recipients.import', 'throttle:mutations'])->group(function () {
    Route::get('/recipient-lists/create', [RecipientListController::class, 'create'])->name('recipient-lists.create');
    Route::delete('/recipient-lists/{recipientList}', [RecipientListController::class, 'destroy'])->name('recipient-lists.destroy');
});

Route::get('/campaigns', [CampaignController::class, 'index'])->middleware('auth')->name('campaigns.index');
Route::get('/campaigns/create', CampaignBuilder::class)->middleware(['auth', 'privilege:campaigns.send'])->name('campaigns.create');
Route::get('/campaigns/test-send', TestEmailSender::class)->middleware(['auth', 'privilege:test-email.send'])->name('campaigns.test-send');
Route::post('/campaigns/test-send/prefill', [CampaignController::class, 'prefillTestSend'])
    ->middleware(['auth', 'privilege:test-email.send', 'throttle:mutations'])->name('campaigns.test-send.prefill');
Route::get('/campaigns/sent-mail', [CampaignController::class, 'sentMail'])->middleware('auth')->name('campaigns.sent-mail');
Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('auth')->name('campaigns.show');
Route::post('/campaigns/{campaign}/recipients/{recipient}/retry', [CampaignController::class, 'retryRecipient'])
    ->middleware(['auth', 'privilege:campaigns.send', 'throttle:mutations'])->name('campaigns.retry-recipient');

// ── Admin: activity log — SuperAdmin + anyone granted activity-logs.view ────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'privilege:activity-logs.view', 'throttle:mutations'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::post('/activity-logs/filters', [ActivityLogController::class, 'updateFilters'])->name('activity.filters');
});

// ── Admin: sections, mail accounts, designations, users — SuperAdmin, or the matching
//    privilege (privilege:X already lets SuperAdmin through too, via User::hasPrivilege()) ──
Route::prefix('admin')->name('admin.')->middleware(['auth', 'throttle:mutations'])->group(function () {
    Route::resource('sections', SectionController::class)->except(['show'])
        ->middleware('privilege:sections.manage');
    Route::resource('mail-accounts', MailAccountController::class)->except(['show'])
        ->parameters(['mail-accounts' => 'mailAccount'])->middleware('privilege:mail-accounts.manage');
    Route::resource('designations', DesignationController::class)->except(['show'])
        ->middleware('privilege:designations.manage');

    Route::prefix('users')->name('users.')->middleware('privilege:users.manage')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/resend-activation', [UserManagementController::class, 'resendActivation'])->name('resend-activation');
    });
});
