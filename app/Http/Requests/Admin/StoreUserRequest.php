<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'digits:10'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'post' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'exists:sections,id'],
            // No password field — the account is passwordless until the officer completes
            // onboarding via their emailed signed link (see OnboardingController).
            'role' => ['required', 'in:SuperAdmin,Admin,User'],
            'privileges' => ['nullable', 'array'],
            'privileges.*' => ['string', 'in:'.implode(',', User::PRIVILEGES)],
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
