<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Http\Requests\AppealRequest;
use App\Models\Appeal;
use App\Models\ViolationRecord;
use App\Services\ViolationWorkflowService;
use Illuminate\Http\RedirectResponse;

class AppealController extends Controller
{
    /** Admin logs an appeal on behalf of the student. */
    public function store(AppealRequest $request, ViolationRecord $record, ViolationWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->fileAppeal($request->user(), $record, $request->input('statement'));
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appeal logged. The original record is unchanged until a decision is made.');
    }

    public function decide(AppealRequest $request, Appeal $appeal, ViolationWorkflowService $workflow): RedirectResponse
    {
        try {
            $workflow->decideAppeal($request->user(), $appeal, $request->input('decision'), $request->input('remarks'));
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('records.show', $appeal->violation_record_id)
            ->with('success', 'Appeal decision recorded.');
    }
}
