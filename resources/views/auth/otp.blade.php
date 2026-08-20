<!DOCTYPE html>
<html lang="en" class="h-full{{ request()->cookie('color_scheme') === 'dark' ? ' dark' : '' }}">

<x-head title="Enter Code" description="UP Department of Excise Mailer sign in." />

<body class="bg-slate-100 dark:bg-slate-950 h-full flex items-center justify-center p-4 transition-colors duration-200">

<div class="w-full max-w-md">

    <div class="text-center mb-8">
        <div class="w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center mx-auto mb-4 shadow-lg">
            <i class="ti ti-shield-lock text-white text-2xl"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Check your email</h1>
        <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Enter the 6-digit code we just sent you</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-8">

        @if ($errors->any())
        <div class="mb-4 flex items-start gap-2 text-sm text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg px-4 py-3">
            <i class="ti ti-alert-circle flex-shrink-0 mt-0.5"></i>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif
        @if (session('flasher'))
        <div class="mb-4 flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 rounded-lg px-4 py-3">
            <i class="ti ti-circle-check flex-shrink-0"></i>
            {{ session('flasher')->messages()->first()?->message() }}
        </div>
        @endif

        <form method="POST" action="{{ route('otp.verify') }}" class="space-y-4">
            @csrf
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required
                class="w-full text-center tracking-[0.5em] text-2xl font-semibold field-input">
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                <i class="ti ti-shield-check"></i>
                Verify &amp; sign in
            </button>
        </form>

        <form method="POST" action="{{ route('otp.resend') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:underline">Resend code</button>
        </form>
    </div>

</div>

</body>
</html>
