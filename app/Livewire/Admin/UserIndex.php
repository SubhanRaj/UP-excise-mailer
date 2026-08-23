<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPrivilege('users.manage'), 403);
    }

    public function delete(int $userId): void
    {
        abort_unless(auth()->user()->hasPrivilege('users.manage'), 403);

        $user = User::findOrFail($userId);
        abort_if(! auth()->user()->isAdmin() && $user->isAdmin(), 403);

        if ($user->id === auth()->id()) {
            flash()->warning('You cannot deactivate your own account.');

            return;
        }

        $user->delete();
        flash()->success('User deactivated.');
    }

    public function render()
    {
        return view('livewire.admin.user-index', [
            'users' => User::with(['designation', 'section'])->orderBy('name')->paginate(20),
        ])->layout('components.layout', ['pageTitle' => 'Users', 'title' => 'Users']);
    }
}
