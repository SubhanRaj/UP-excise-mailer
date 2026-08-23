<?php

namespace App\Livewire\Admin;

use App\Models\Designation;
use Livewire\Component;

class DesignationIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasPrivilege('designations.manage'), 403);
    }

    public function delete(int $designationId): void
    {
        abort_unless(auth()->user()->hasPrivilege('designations.manage'), 403);

        Designation::findOrFail($designationId)->delete();
        flash()->success('Designation deleted — existing users keep whatever privileges they already have.');
    }

    public function render()
    {
        return view('livewire.admin.designation-index', [
            'designations' => Designation::orderBy('sort_order')->orderBy('name')->get(),
        ])->layout('components.layout', ['pageTitle' => 'Designations', 'title' => 'Designations']);
    }
}
