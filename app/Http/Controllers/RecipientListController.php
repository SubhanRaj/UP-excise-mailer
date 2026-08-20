<?php

namespace App\Http\Controllers;

use App\Models\RecipientList;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RecipientListController extends Controller
{
    public function index(): View
    {
        $lists = RecipientList::withCount('items')->with('uploadedBy')->latest()->paginate(20);

        return view('recipient-lists.index', compact('lists'));
    }

    public function create(): View
    {
        return view('recipient-lists.create');
    }

    public function show(RecipientList $recipientList): View
    {
        $items = $recipientList->items()->paginate(50);

        return view('recipient-lists.show', ['list' => $recipientList, 'items' => $items]);
    }

    public function destroy(RecipientList $recipientList): RedirectResponse
    {
        abort_unless(request()->user()->hasPrivilege('recipients.import'), 403);

        $recipientList->delete();

        flash()->success('Recipient list deleted.');

        return redirect()->route('recipient-lists.index');
    }
}
