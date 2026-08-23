<?php

namespace App\Livewire\Admin;

use App\Models\Section;
use Livewire\Component;

class SectionIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasPrivilege('sections.manage'), 403);
    }

    public function delete(int $sectionId): void
    {
        abort_unless(auth()->user()->hasPrivilege('sections.manage'), 403);

        $section = Section::findOrFail($sectionId);

        if ($section->users()->exists() || $section->mailAccounts()->exists()) {
            flash()->warning('This section still has users or mail accounts attached — reassign them first.');

            return;
        }

        $section->delete();
        flash()->success('Section deleted.');
    }

    public function render()
    {
        return view('livewire.admin.section-index', [
            'sections' => Section::withCount(['users', 'mailAccounts'])->orderBy('name')->get(),
        ])->layout('components.layout', ['pageTitle' => 'Sections', 'title' => 'Sections']);
    }
}
