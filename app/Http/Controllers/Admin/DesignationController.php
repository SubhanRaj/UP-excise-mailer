<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDesignationRequest;
use App\Http\Requests\Admin\UpdateDesignationRequest;
use App\Models\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(): View
    {
        $designations = Designation::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.designations.index', compact('designations'));
    }

    public function create(): View
    {
        return view('admin.designations.create');
    }

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        Designation::create($request->validated());

        flash()->success('Designation created.');

        return redirect()->route('admin.designations.index');
    }

    public function edit(Designation $designation): View
    {
        return view('admin.designations.edit', compact('designation'));
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated());

        flash()->success('Designation updated.');

        return redirect()->route('admin.designations.index');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $designation->delete();

        flash()->success('Designation deleted — existing users keep whatever privileges they already have.');

        return redirect()->route('admin.designations.index');
    }
}
