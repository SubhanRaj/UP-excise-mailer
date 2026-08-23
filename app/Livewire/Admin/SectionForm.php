<?php

namespace App\Livewire\Admin;

use App\Models\Section;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SectionForm extends Component
{
    public ?Section $section = null;

    public string $name = '';

    public string $email = '';

    public string $headName = '';

    public function mount(?Section $section = null): void
    {
        abort_unless(auth()->user()->hasPrivilege('sections.manage'), 403);

        $this->section = $section;
        $this->name = $section->name ?? '';
        $this->email = $section->email ?? '';
        $this->headName = $section->head_name ?? '';
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPrivilege('sections.manage'), 403);

        $this->name = strip_tags($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('sections', 'name')->ignore($this->section?->id)],
            'email' => ['nullable', 'email', 'max:150'],
            'headName' => ['nullable', 'string', 'max:150'],
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'email' => $validated['email'] ?: null,
            'head_name' => $validated['headName'] ?: null,
        ];

        if ($this->section) {
            $this->section->update($data);
            flash()->success('Section updated.');
        } else {
            Section::create($data);
            flash()->success('Section created.');
        }

        $this->redirectRoute('admin.sections.index', navigate: true);
    }

    public function render()
    {
        $title = $this->section ? "Edit {$this->section->name}" : 'Add Section';

        return view('livewire.admin.section-form')
            ->layout('components.layout', ['pageTitle' => $title, 'title' => $title]);
    }
}
