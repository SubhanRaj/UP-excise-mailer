<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMailAccountRequest;
use App\Http\Requests\Admin\UpdateMailAccountRequest;
use App\Models\MailAccount;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MailAccountController extends Controller
{
    public function index(): View
    {
        $mailAccounts = MailAccount::with('section')->orderBy('gmail_address')->get();

        return view('admin.mail-accounts.index', compact('mailAccounts'));
    }

    public function create(): View
    {
        $sections = Section::orderBy('name')->get();

        return view('admin.mail-accounts.create', compact('sections'));
    }

    public function store(StoreMailAccountRequest $request): RedirectResponse
    {
        MailAccount::create($request->validated());

        flash()->success('Mail account created.');

        return redirect()->route('admin.mail-accounts.index');
    }

    public function edit(MailAccount $mailAccount): View
    {
        $sections = Section::orderBy('name')->get();

        return view('admin.mail-accounts.edit', compact('mailAccount', 'sections'));
    }

    public function update(UpdateMailAccountRequest $request, MailAccount $mailAccount): RedirectResponse
    {
        $validated = $request->validated();

        // Blank app_password on the edit form means "keep the existing one" — never overwrite
        // a working credential with an empty string.
        if (empty($validated['app_password'])) {
            unset($validated['app_password']);
        }

        $mailAccount->update($validated);

        flash()->success('Mail account updated.');

        return redirect()->route('admin.mail-accounts.index');
    }

    public function destroy(MailAccount $mailAccount): RedirectResponse
    {
        $mailAccount->delete();

        flash()->success('Mail account deleted.');

        return redirect()->route('admin.mail-accounts.index');
    }
}
