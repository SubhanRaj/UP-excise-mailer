<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\MailTemplateController;
use App\Livewire\Admin\DesignationForm;
use App\Livewire\Admin\DesignationIndex;
use App\Livewire\Admin\MailAccountForm;
use App\Livewire\Admin\MailAccountIndex;
use App\Livewire\Admin\SectionForm;
use App\Livewire\Admin\SectionIndex;
use App\Livewire\Admin\UserForm;
use App\Livewire\Admin\UserIndex;
use App\Livewire\CampaignBuilder;
use App\Livewire\CampaignShow;
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

Route::prefix('recipients')->name('recipients.')->middleware(['auth', 'privilege:recipients.manage', 'throttle:mutations'])->group(function () {
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
Route::middleware(['auth', 'privilege:recipients.import', 'throttle:mutations'])->group(function () {
    Route::get('/recipient-lists/create', [RecipientListController::class, 'create'])->name('recipient-lists.create');
    Route::delete('/recipient-lists/{recipientList}', [RecipientListController::class, 'destroy'])->name('recipient-lists.destroy');
});
Route::get('/recipient-lists/{recipientList}', [RecipientListController::class, 'show'])->middleware('auth')->name('recipient-lists.show');

Route::get('/campaigns', [CampaignController::class, 'index'])->middleware('auth')->name('campaigns.index');
Route::get('/campaigns/sent-mail', [CampaignController::class, 'sentMail'])->middleware('auth')->name('campaigns.sent-mail');

// Literal-path routes (create, test-send) must be registered before the campaigns/{campaign}
// wildcard below — otherwise Laravel matches the wildcard first and "test-send"/"create" get
// treated as a campaign slug, 404ing on CampaignShow's implicit route binding instead of ever
// reaching these routes at all.
Route::middleware(['auth', 'privilege:campaigns.send'])->group(function () {
    Route::get('/campaigns/create', CampaignBuilder::class)->name('campaigns.create');
});

Route::middleware(['auth', 'privilege:test-email.send'])->group(function () {
    Route::get('/campaigns/test-send', TestEmailSender::class)->name('campaigns.test-send');
    Route::post('/campaigns/test-send/prefill', [CampaignController::class, 'prefillTestSend'])
        ->middleware('throttle:mutations')->name('campaigns.test-send.prefill');
});

// Full Livewire component — retry/resend/mark-sent/mark-responded/fetch-replies/export are all
// wire:click actions on it; Livewire's SupportFileDownloads feature turns a returned
// StreamedResponse into a real browser download without leaving the AJAX flow.
Route::get('/campaigns/{campaign}', CampaignShow::class)->middleware('auth')->name('campaigns.show');

// ── Admin: activity log — SuperAdmin + anyone granted activity-logs.view ────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'privilege:activity-logs.view', 'throttle:mutations'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::post('/activity-logs/filters', [ActivityLogController::class, 'updateFilters'])->name('activity.filters');
});

// ── Admin: sections, mail accounts, designations, users — SuperAdmin, or the matching
//    privilege (privilege:X already lets SuperAdmin through too, via User::hasPrivilege()).
//    All full Livewire components now — index/create/edit are GET routes mounting a component
//    (still route-middleware-gated for the page load itself), but store/update/destroy/
//    resend-activation are gone as separate routes: those are wire:click/wire:submit actions on
//    the component now, each independently privilege-checked (see SECURITY.md L-02) since
//    Livewire's own update endpoint doesn't run this group's middleware. ──
Route::prefix('admin')->name('admin.')->middleware(['auth', 'throttle:mutations'])->group(function () {
    Route::middleware('privilege:sections.manage')->group(function () {
        Route::get('/sections', SectionIndex::class)->name('sections.index');
        Route::get('/sections/create', SectionForm::class)->name('sections.create');
        Route::get('/sections/{section}/edit', SectionForm::class)->name('sections.edit');
    });

    Route::middleware('privilege:mail-accounts.manage')->group(function () {
        Route::get('/mail-accounts', MailAccountIndex::class)->name('mail-accounts.index');
        Route::get('/mail-accounts/create', MailAccountForm::class)->name('mail-accounts.create');
        Route::get('/mail-accounts/{mailAccount}/edit', MailAccountForm::class)->name('mail-accounts.edit');
    });

    Route::middleware('privilege:designations.manage')->group(function () {
        Route::get('/designations', DesignationIndex::class)->name('designations.index');
        Route::get('/designations/create', DesignationForm::class)->name('designations.create');
        Route::get('/designations/{designation}/edit', DesignationForm::class)->name('designations.edit');
    });

    Route::prefix('users')->name('users.')->middleware('privilege:users.manage')->group(function () {
        Route::get('/', UserIndex::class)->name('index');
        Route::get('/create', UserForm::class)->name('create');
        Route::get('/{user}/edit', UserForm::class)->name('edit');
    });
});
