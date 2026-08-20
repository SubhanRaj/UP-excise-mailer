<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPrivilege('users.manage') ?? false;
    }

    /**
     * A users.manage privilege holder (not SuperAdmin) can't promote anyone to SuperAdmin or
     * grant a privilege they don't themselves hold — otherwise the privilege would be a route
     * to unlimited self-escalation.
     */
    public function rules(): array
    {
        $actor = $this->user();
        $roles = $actor?->isAdmin() ? ['SuperAdmin', 'Admin', 'User'] : ['Admin', 'User'];
        $grantablePrivileges = $actor?->isAdmin() ? User::PRIVILEGES : array_intersect(User::PRIVILEGES, $actor?->privileges ?? []);

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'digits:10'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'post' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'exists:sections,id'],
            // No password field — the account is passwordless until the officer completes
            // onboarding via their emailed signed link (see OnboardingController).
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
