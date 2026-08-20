<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'exists:sections,id'],
            'gmail_address' => ['required', 'email', 'max:255'],
            'app_password' => ['required', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'throttle_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'daily_send_cap' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gmail_address' => strtolower((string) $this->input('gmail_address')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
