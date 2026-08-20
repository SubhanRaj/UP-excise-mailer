<!DOCTYPE html>
<html lang="en" class="h-full{{ request()->cookie('color_scheme') === 'dark' ? ' dark' : '' }}">

<x-head title="Sign In" description="UP Department of Excise Mailer sign in." />

<body class="bg-slate-100 dark:bg-slate-950 h-full flex items-center justify-center p-4 transition-colors duration-200">

<div class="w-full max-w-md">

    <div class="text-center mb-8">
        <div class="w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center mx-auto mb-4 shadow-lg">
            <i class="ti ti-mail-forward text-white text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">UP Excise Mailer</h1>
        <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">UP Department of Excise</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-8">

        <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100 mb-1">Sign in to your account</h2>
        <p class="text-sm text-slate-400 dark:text-slate-500 mb-6">Enter your credentials to receive a one-time code.</p>

        @if(session('status'))
        <div class="mb-4 flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-lg px-4 py-3">
            <i class="ti ti-circle-check flex-shrink-0"></i>
            {{ session('status') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-4 flex items-start gap-2 text-sm text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg px-4 py-3">
            <i class="ti ti-alert-circle flex-shrink-0 mt-0.5"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="field-label">Email address</label>
                <div class="relative">
                    <i class="ti ti-mail absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-sm pointer-events-none"></i>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        autocomplete="email" placeholder="you@exciseup.in"
                        class="field-input pl-9 @error('email') field-error @enderror">
                </div>
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <div class="relative">
                    <i class="ti ti-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-sm pointer-events-none"></i>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        placeholder="••••••••" class="field-input pl-9 @error('password') field-error @enderror">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                <input type="checkbox" name="remember" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500">
                Remember me
            </label>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-colors flex items-center justify-center gap-2 mt-2">
                <i class="ti ti-login-2"></i>
                Send sign-in code
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-slate-400 dark:text-slate-600 mt-6">
        Internal use only &middot; Unauthorized access is prohibited
    </p>

</div>

</body>
</html>
