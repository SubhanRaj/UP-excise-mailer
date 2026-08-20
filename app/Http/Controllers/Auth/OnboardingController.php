<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            flash()->success('This account is already active — please sign in.');

            return redirect()->route('login');
        }

        return view('auth.onboarding', ['user' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            flash()->success('This account is already active — please sign in.');

            return redirect()->route('login');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ])->save();

        flash()->success('Account activated — please sign in with your new password.');

        return redirect()->route('login');
    }
}
