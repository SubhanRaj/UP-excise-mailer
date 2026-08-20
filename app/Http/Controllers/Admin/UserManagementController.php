<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Mail\AccountOnboarding;
use App\Models\Designation;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::with(['designation', 'section'])->orderBy('name')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $designations = Designation::orderBy('sort_order')->orderBy('name')->get();
        $sections = Section::orderBy('name')->get();

        return view('admin.users.create', compact('designations', 'sections'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    ...$validated,
                    'username' => User::uniqueUsername($validated['name']),
                    // Unusable placeholder — never surfaced anywhere. The officer sets their
                    // own real password via the onboarding link mailed below.
                    'password' => Hash::make(Str::random(40)),
                    // Left null on purpose — doubles as the onboarding link's single-use gate
                    // (OnboardingController redirects to login once this is set) and as a
                    // genuine "this address was actually confirmed" flag.
                    'email_verified_at' => null,
                ]);
            });

            // Account already exists at this point — a mail failure here must not be reported
            // as account-creation failure (the admin would retry and hit the unique-email
            // constraint). Log it and let them use "Resend activation link" instead.
            try {
                $this->sendOnboardingLink($user);
                flash()->success("Account for {$user->name} created — an activation email has been sent.");
            } catch (\Throwable $mailError) {
                Log::error('UserManagementController@store: onboarding mail failed', [
                    'user_id' => $user->id,
                    'error' => $mailError->getMessage(),
                ]);
                flash()->warning("Account for {$user->name} created, but the activation email failed to send. Use \"Resend activation link\" on their profile.");
            }
        } catch (\Throwable $e) {
            Log::error('User creation failed', ['error' => $e->getMessage()]);
            flash()->error('Something went wrong creating the user.');
        }

        return redirect()->route('admin.users.index');
    }

    /** Re-send the onboarding link for a user who never completed activation. */
    public function resendActivation(User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            flash()->warning("{$user->name}'s account is already active.");

            return back();
        }

        $this->sendOnboardingLink($user);

        flash()->success("Activation email re-sent to {$user->email}.");

        return back();
    }

    private function sendOnboardingLink(User $user): void
    {
        $url = URL::temporarySignedRoute('onboarding.show', now()->addHours(72), ['user' => $user->id]);

        Mail::to($user->email)->send(new AccountOnboarding($user, $url));
    }

    public function edit(User $user): View
    {
        $designations = Designation::orderBy('sort_order')->orderBy('name')->get();
        $sections = Section::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'designations', 'sections'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $user) {
                $user->update($validated);
            });

            flash()->success('User updated.');
        } catch (\Throwable $e) {
            Log::error('User update failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            flash()->error('Something went wrong updating the user.');
        }

        return redirect()->route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            flash()->warning('You cannot deactivate your own account.');

            return redirect()->route('admin.users.index');
        }

        $user->delete();
        flash()->success('User deactivated.');

        return redirect()->route('admin.users.index');
    }
}
