<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPrivilege('templates.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
