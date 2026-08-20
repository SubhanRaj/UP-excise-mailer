<?php

namespace App\Providers;

use App\Models\ActivityLog;
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
