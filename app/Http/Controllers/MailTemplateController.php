<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMailTemplateRequest;
use App\Models\MailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MailTemplateController extends Controller
{
    public function index(): View
    {
        $templates = MailTemplate::with('createdBy')->latest()->paginate(20);

        return view('templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('templates.create');
    }

    public function store(StoreMailTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        MailTemplate::create([
            ...$validated,
            'variables' => $this->extractVariables($validated['subject'].' '.$validated['body']),
            'created_by' => $request->user()->id,
        ]);

        flash()->success('Template created.');

        return redirect()->route('templates.index');
    }

    public function edit(MailTemplate $template): View
    {
        return view('templates.edit', compact('template'));
    }

    public function update(StoreMailTemplateRequest $request, MailTemplate $template): RedirectResponse
    {
        $validated = $request->validated();

        $template->update([
            ...$validated,
            'variables' => $this->extractVariables($validated['subject'].' '.$validated['body']),
        ]);

        flash()->success('Template updated.');

        return redirect()->route('templates.index');
    }

    public function destroy(MailTemplate $template): RedirectResponse
    {
        abort_unless(request()->user()->hasPrivilege('templates.manage'), 403);

        $template->delete();

        flash()->success('Template deleted.');

        return redirect()->route('templates.index');
    }

    /** Pulls {{variable}} names out of the template text so the builder can list them without re-parsing. */
    private function extractVariables(string $text): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
