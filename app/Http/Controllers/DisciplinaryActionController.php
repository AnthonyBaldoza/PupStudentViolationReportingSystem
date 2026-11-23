<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisciplinaryActionRequest;
use App\Models\DisciplinaryAction;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DisciplinaryActionController extends Controller
{
    public function index(): View
    {
        return view('actions.index', [
            'actions' => DisciplinaryAction::withCount('rules')->orderBy('name')->get(),
        ]);
    }

    public function store(DisciplinaryActionRequest $request, AuditLogger $audit): RedirectResponse
    {
        $action = DisciplinaryAction::create($request->validated() + ['is_active' => true]);
        $audit->log('action.created', $action, null, $action->only(['name', 'description']));

        return back()->with('success', 'Disciplinary action added.');
    }

    public function update(DisciplinaryActionRequest $request, DisciplinaryAction $disciplinaryAction, AuditLogger $audit): RedirectResponse
    {
        $disciplinaryAction->fill($request->validated());
        $audit->saveWithAudit($disciplinaryAction, 'action.updated');

        return back()->with('success', 'Disciplinary action updated. Approved records keep the wording they were approved with.');
    }

    public function toggle(DisciplinaryAction $disciplinaryAction, AuditLogger $audit): RedirectResponse
    {
        $disciplinaryAction->is_active = ! $disciplinaryAction->is_active;
        $audit->saveWithAudit($disciplinaryAction, $disciplinaryAction->is_active ? 'action.activated' : 'action.deactivated');

        return back()->with('success', $disciplinaryAction->is_active
            ? 'Action activated.'
            : 'Action deactivated. It can no longer be chosen for new rules; existing rules keep working.');
    }
}
