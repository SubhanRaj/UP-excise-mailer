<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Livewire's temporary-file-upload endpoint is registered globally at boot,
        // independent of any page route's middleware — without this it defaults to
        // 'throttle:60,1' with no auth at all, letting anyone POST files to
        // storage/app/private/livewire-tmp/ regardless of whether they can reach any page
        // that actually uses WithFileUploads (recipient/officer directory imports, campaign
        // zip attachments).
        config(['livewire.temporary_file_upload.middleware' => ['auth', 'throttle:60,1']]);

        // Timestamps stay stored in UTC (config('app.timezone'), correct for sorting/DST-safety
        // and to not silently mislabel every already-stored row) — this only converts at
        // display time. ->ist()->format(...) everywhere a Blade view shows a date; never
        // change app.timezone itself, or every existing row's displayed time shifts by 5:30
        // without the underlying data actually changing.
        Carbon::macro('ist', function () {
            /** @var Carbon $this */
            return $this->copy()->timezone('Asia/Kolkata');
        });

        $this->configureRateLimiters();
        $this->configureActivityLogging();
    }

    private function configureRateLimiters(): void
    {
        // Keyed by email+IP (targeted) AND IP alone (broad), whichever hits first.
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->session()->get('login.id').'|'.$request->ip());
        });

        RateLimiter::for('mutations', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function configureActivityLogging(): void
    {
        Event::listen(Login::class, function (Login $event) {
            ActivityLog::record('auth.login', request(), ['guard' => $event->guard]);
        });

        // Fired inside Auth::logout() after the guard has already cleared the authenticated
        // user, so auth()->id() would be null by now — $event->user carries the actor instead.
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                ActivityLog::record('auth.logout', request(), [], $event->user->id);
            }
        });
    }
}
