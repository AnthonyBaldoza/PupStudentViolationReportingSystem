<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Http\Requests\ReviewRequest;
use App\Models\ViolationRecord;
use App\Services\ViolationWorkflowService;
use Closure;
use Illuminate\Http\RedirectResponse;

/** Admin-only review actions. All business rules live in ViolationWorkflowService. */
class ReviewController extends Controller
{
    public function approve(ReviewRequest $request, ViolationRecord $record, ViolationWorkflowService $workflow): RedirectResponse
    {
        return $this->run($record, fn () => $workflow->approve($request->user(), $record, $request->input('remarks')),
            'Report approved. The offense number and disciplinary action were assigned and added to the student\'s history.');
    }

    public function reject(ReviewRequest $request, ViolationRecord $record, ViolationWorkflowService $workflow): RedirectResponse
    {
        return $this->run($record, fn () => $workflow->reject($request->user(), $record, $request->input('remarks')),
            'Report rejected. It does not count toward any offense total.');
    }

    public function return(ReviewRequest $request, ViolationRecord $record, ViolationWorkflowService $workflow): RedirectResponse
    {
        return $this->run($record, fn () => $workflow->returnForInfo($request->user(), $record, $request->input('remarks')),
            'Report returned to the officer for more information.');
    }

    private function run(ViolationRecord $record, Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (WorkflowException $e) {
            return redirect()->route('records.show', $record)->with('error', $e->getMessage());
        }

        return redirect()->route('records.show', $record)->with('success', $success);
    }
}
