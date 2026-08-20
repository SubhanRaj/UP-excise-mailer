<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:designations,name'],
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
