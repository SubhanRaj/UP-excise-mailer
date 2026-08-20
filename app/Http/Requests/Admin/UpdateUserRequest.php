<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'mobile' => ['nullable', 'digits:10'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'post' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'exists:sections,id'],
            // No password field — admins can never set/reset a user's password. A locked-out
            // user gets a fresh onboarding link via resendActivation(), same as a new account.
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
