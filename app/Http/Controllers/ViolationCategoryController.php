<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\DisciplinaryAction;
use App\Models\ViolationCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ViolationCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ViolationCategory::query()
            ->withCount(['rules', 'records'])
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new ViolationCategory(['is_active' => true]), 'actions' => collect()]);
    }

    public function store(CategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        $category = ViolationCategory::create($request->validated() + ['is_active' => true]);
        $audit->log('category.created', $category, null, $category->only(['name', 'description']));

        return redirect()->route('categories.edit', $category)
            ->with('success', 'Category created. Now configure its offense rules below.');
    }

    public function edit(ViolationCategory $category): View
    {
        return view('categories.form', [
            'category' => $category->load('rules.action'),
            'actions' => DisciplinaryAction::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(CategoryRequest $request, ViolationCategory $category, AuditLogger $audit): RedirectResponse
    {
        $category->fill($request->validated());
        $audit->saveWithAudit($category, 'category.updated');

        return back()->with('success', 'Category updated. Existing approved records keep the name they were approved under.');
    }

    /** Categories are never deleted: deactivating stops NEW reports but keeps all history. */
    public function toggle(ViolationCategory $category, AuditLogger $audit): RedirectResponse
    {
        $category->is_active = ! $category->is_active;
        $audit->saveWithAudit($category, $category->is_active ? 'category.activated' : 'category.deactivated');

        return back()->with('success', $category->is_active ? 'Category activated.' : 'Category deactivated. History is preserved.');
    }
}
