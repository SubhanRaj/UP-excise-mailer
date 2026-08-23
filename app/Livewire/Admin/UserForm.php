<?php

namespace App\Livewire\Admin;

use App\Mail\AccountOnboarding;
use App\Models\Designation;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $mobile = '';

    public ?int $designationId = null;

    public string $post = '';

    public ?int $sectionId = null;

    public string $role = 'User';

    public array $privileges = [];

    public function mount(?User $user = null): void
    {
        abort_unless(auth()->user()->hasPrivilege('users.manage'), 403);
        abort_if($user && ! auth()->user()->isAdmin() && $user->isAdmin(), 403);

        $this->user = $user;
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->designationId = $user->designation_id ?? null;
        $this->post = $user->post ?? '';
        $this->sectionId = $user->section_id ?? null;
        $this->role = $user->role ?? 'User';
        $this->privileges = $user->privileges ?? [];
    }

    /** Selecting a designation fills in its default privileges — an editable starting point, not a lock. */
    public function updatedDesignationId(): void
    {
        $this->privileges = $this->designationId
            ? (Designation::find($this->designationId)?->default_privileges ?? [])
            : $this->privileges;
    }

    /**
     * Same escalation guard as the old StoreUserRequest/UpdateUserRequest: a users.manage
     * privilege holder who isn't SuperAdmin can't promote anyone to SuperAdmin or grant a
     * privilege they don't themselves hold.
     */
    public function save(): void
    {
        abort_unless(auth()->user()->hasPrivilege('users.manage'), 403);
        abort_if($this->user && ! auth()->user()->isAdmin() && $this->user->isAdmin(), 403);

        $actor = auth()->user();
        $roles = $actor->isAdmin() ? ['SuperAdmin', 'Admin', 'User'] : ['Admin', 'User'];
        $grantablePrivileges = $actor->isAdmin() ? User::PRIVILEGES : array_intersect(User::PRIVILEGES, $actor->privileges ?? []);

        $this->name = strip_tags($this->name);
        $this->email = strtolower($this->email);
        $this->mobile = $this->mobile ? preg_replace('/\D/', '', $this->mobile) : '';

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'mobile' => ['nullable', 'digits:10'],
            'designationId' => ['nullable', 'exists:designations,id'],
            'post' => ['nullable', 'string', 'max:100'],
            'sectionId' => ['nullable', 'exists:sections,id'],
            'role' => ['required', 'in:'.implode(',', $roles)],
            'privileges' => ['nullable', 'array'],
            'privileges.*' => ['string', 'in:'.implode(',', $grantablePrivileges)],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?: null,
            'designation_id' => $validated['designationId'],
            'post' => $validated['post'] ?: null,
            'section_id' => $validated['sectionId'],
            'role' => $validated['role'],
            'privileges' => $validated['privileges'] ?? [],
        ];

        if ($this->user) {
            try {
                DB::transaction(fn () => $this->user->update($data));
                flash()->success('User updated.');
            } catch (\Throwable $e) {
                Log::error('User update failed', ['error' => $e->getMessage(), 'user_id' => $this->user->id]);
                flash()->error('Something went wrong updating the user.');

                return;
            }
        } else {
            try {
                $newUser = DB::transaction(function () use ($data) {
                    return User::create([
                        ...$data,
                        'username' => User::uniqueUsername($data['name']),
                        // Unusable placeholder — never surfaced anywhere. The officer sets their
                        // own real password via the onboarding link mailed below.
                        'password' => Hash::make(Str::random(40)),
                        // Left null on purpose — doubles as the onboarding link's single-use gate
                        // and as a genuine "this address was actually confirmed" flag.
                        'email_verified_at' => null,
                    ]);
                });

                try {
                    $this->sendOnboardingLink($newUser);
                    flash()->success("Account for {$newUser->name} created — an activation email has been sent.");
                } catch (\Throwable $mailError) {
                    Log::error('UserForm@save: onboarding mail failed', ['user_id' => $newUser->id, 'error' => $mailError->getMessage()]);
                    flash()->warning("Account for {$newUser->name} created, but the activation email failed to send. Use \"Resend activation link\" on their profile.");
                }
            } catch (\Throwable $e) {
                Log::error('User creation failed', ['error' => $e->getMessage()]);
                flash()->error('Something went wrong creating the user.');

                return;
            }
        }

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function resendActivation(): void
    {
        abort_unless(auth()->user()->hasPrivilege('users.manage'), 403);
        abort_if(! $this->user, 404);
        abort_if(! auth()->user()->isAdmin() && $this->user->isAdmin(), 403);

        if ($this->user->email_verified_at !== null) {
            flash()->warning("{$this->user->name}'s account is already active.");

            return;
        }

        $this->sendOnboardingLink($this->user);
        flash()->success("Activation email re-sent to {$this->user->email}.");
    }

    private function sendOnboardingLink(User $user): void
    {
        $url = URL::temporarySignedRoute('onboarding.show', now()->addHours(72), ['user' => $user->id]);

        Mail::to($user->email)->send(new AccountOnboarding($user, $url));
    }

    public function render()
    {
        $title = $this->user ? "Edit {$this->user->name}" : 'Add User';

        return view('livewire.admin.user-form', [
            'designations' => Designation::orderBy('sort_order')->orderBy('name')->get(),
            'sections' => Section::orderBy('name')->get(),
        ])->layout('components.layout', ['pageTitle' => $title, 'title' => $title]);
    }
}
