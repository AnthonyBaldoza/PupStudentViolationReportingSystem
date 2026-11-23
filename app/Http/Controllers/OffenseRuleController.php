<?php

namespace App\Http\Controllers;

use App\Http\Requests\OffenseRuleRequest;
use App\Models\OffenseRule;
use App\Models\ViolationCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class OffenseRuleController extends Controller
{
    public function store(OffenseRuleRequest $request, ViolationCategory $category, AuditLogger $audit): RedirectResponse
    {
        $rule = $category->rules()->create([
            'offense_number' => $request->integer('offense_number'),
            'is_succeeding' => $request->boolean('is_succeeding'),
            'description' => $request->input('description'),
            'disciplinary_action_id' => $request->integer('disciplinary_action_id'),
            'is_active' => true,
        ]);

        $audit->log('rule.created', $rule, null, $rule->only(['violation_category_id', 'offense_number', 'is_succeeding', 'disciplinary_action_id']));

        return back()->with('success', 'Offense rule added.');
    }

    /** Edits only affect FUTURE approvals; approved records keep their own snapshot. */
    public function update(OffenseRuleRequest $request, OffenseRule $rule, AuditLogger $audit): RedirectResponse
    {
        $rule->fill([
            'offense_number' => $request->integer('offense_number'),
            'is_succeeding' => $request->boolean('is_succeeding'),
            'description' => $request->input('description'),
            'disciplinary_action_id' => $request->integer('disciplinary_action_id'),
        ]);
        $audit->saveWithAudit($rule, 'rule.updated');

        return redirect()->route('categories.edit', $rule->violation_category_id)
            ->with('success', 'Offense rule updated. Already-approved records are not changed.');
    }

    public function toggle(OffenseRule $rule, AuditLogger $audit): RedirectResponse
    {
        $rule->is_active = ! $rule->is_active;
        $audit->saveWithAudit($rule, $rule->is_active ? 'rule.activated' : 'rule.deactivated');

        return back()->with('success', $rule->is_active ? 'Rule activated.' : 'Rule deactivated. Past records are unaffected.');
    }
}
