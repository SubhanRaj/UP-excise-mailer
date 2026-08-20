<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(): View
    {
        $sections = Section::withCount(['users', 'mailAccounts'])->orderBy('name')->get();

        return view('admin.sections.index', compact('sections'));
    }

    public function create(): View
    {
        return view('admin.sections.create');
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        Section::create($request->validated());

        flash()->success('Section created.');

        return redirect()->route('admin.sections.index');
    }

    public function edit(Section $section): View
    {
        return view('admin.sections.edit', compact('section'));
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        flash()->success('Section updated.');

        return redirect()->route('admin.sections.index');
    }

    public function destroy(Section $section): RedirectResponse
    {
        if ($section->users()->exists() || $section->mailAccounts()->exists()) {
            flash()->warning('This section still has users or mail accounts attached — reassign them first.');

            return redirect()->route('admin.sections.index');
        }

        $section->delete();

        flash()->success('Section deleted.');

        return redirect()->route('admin.sections.index');
    }
}
