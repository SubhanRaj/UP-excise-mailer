<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPrivilege('designations.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('designations', 'name')->ignore($this->route('designation'))],
            'slug' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer'],
            'default_privileges' => ['nullable', 'array'],
            'default_privileges.*' => ['string', 'in:'.implode(',', User::PRIVILEGES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags((string) $this->input('name')),
            'slug' => Str::slug((string) $this->input('name')),
        ]);
    }
}
