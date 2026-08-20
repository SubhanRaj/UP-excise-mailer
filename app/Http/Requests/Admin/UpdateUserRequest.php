<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor?->hasPrivilege('users.manage')) {
            return false;
        }

        // A users.manage privilege holder (not SuperAdmin) can't edit a SuperAdmin account —
        // otherwise they could demote/lock out the very account that outranks them.
        $target = $this->route('user');

        return $actor->isAdmin() || ! $target?->isAdmin();
    }

    /**
     * Same escalation guard as StoreUserRequest — a privilege-only actor can't promote anyone
     * to SuperAdmin or grant a privilege they don't themselves hold.
     */
    public function rules(): array
    {
        $actor = $this->user();
        $roles = $actor?->isAdmin() ? ['SuperAdmin', 'Admin', 'User'] : ['Admin', 'User'];
        $grantablePrivileges = $actor?->isAdmin() ? User::PRIVILEGES : array_intersect(User::PRIVILEGES, $actor?->privileges ?? []);

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'mobile' => ['nullable', 'digits:10'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'post' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'exists:sections,id'],
            // No password field — admins can never set/reset a user's password. A locked-out
            // user gets a fresh onboarding link via resendActivation(), same as a new account.
            'role' => ['required', 'in:'.implode(',', $roles)],
            'privileges' => ['nullable', 'array'],
            'privileges.*' => ['string', 'in:'.implode(',', $grantablePrivileges)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags((string) $this->input('name')),
            'email' => strtolower((string) $this->input('email')),
            'mobile' => $this->input('mobile') ? preg_replace('/\D/', '', $this->input('mobile')) : null,
        ]);
    }
}
