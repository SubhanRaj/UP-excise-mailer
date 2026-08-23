<?php

namespace App\Livewire\Admin;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DesignationForm extends Component
{
    public ?Designation $designation = null;

    public string $name = '';

    public ?int $sortOrder = 0;

    public array $defaultPrivileges = [];

    public function mount(?Designation $designation = null): void
    {
        abort_unless(auth()->user()->hasPrivilege('designations.manage'), 403);

        $this->designation = $designation;
        $this->name = $designation->name ?? '';
        $this->sortOrder = $designation->sort_order ?? 0;
        $this->defaultPrivileges = $designation->default_privileges ?? [];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPrivilege('designations.manage'), 403);

        $this->name = strip_tags($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('designations', 'name')->ignore($this->designation?->id)],
            'sortOrder' => ['nullable', 'integer'],
            'defaultPrivileges' => ['nullable', 'array'],
            'defaultPrivileges.*' => ['string', 'in:'.implode(',', User::PRIVILEGES)],
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'sort_order' => $validated['sortOrder'],
            'default_privileges' => $validated['defaultPrivileges'],
        ];

        if ($this->designation) {
            $this->designation->update($data);
            flash()->success('Designation updated.');
        } else {
            Designation::create($data);
            flash()->success('Designation created.');
        }

        $this->redirectRoute('admin.designations.index', navigate: true);
    }

    public function render()
    {
        $title = $this->designation ? "Edit {$this->designation->name}" : 'Add Designation';

        return view('livewire.admin.designation-form')
            ->layout('components.layout', ['pageTitle' => $title, 'title' => $title]);
    }
}
