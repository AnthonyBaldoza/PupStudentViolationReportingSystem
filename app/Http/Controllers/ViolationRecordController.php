<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Http\Requests\ViolationReportRequest;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use App\Services\OffenseService;
use App\Services\ViolationWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ViolationRecordController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $f = $request->validate([
            'status' => ['nullable', Rule::in(ViolationRecord::STATUSES)],
            'category_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $records = ViolationRecord::query()
            ->with(['student.course', 'category', 'reporter'])
            // Officers can only ever list the reports they filed.
            ->when(! $user->isAdmin(), fn ($q) => $q->where('reported_by', $user->id))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('violation_category_id', $v))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('incident_date', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('incident_date', '<=', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->whereIn('student_id', Student::withTrashed()->search($v)->select('id')))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('records.index', [
            'records' => $records,
            'categories' => ViolationCategory::orderBy('name')->get(),
            'filters' => $f,
        ]);
    }

    public function create(Request $request): View
    {
        $student = $request->integer('student')
            ? Student::with('course')->find($request->integer('student'))
            : null;

        return view('records.form', [
            'record' => new ViolationRecord(),
            'student' => $student,
            'categories' => ViolationCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ViolationReportRequest $request, ViolationWorkflowService $workflow): RedirectResponse
    {
        try {
            $record = $workflow->submit($request->user(), $request->validated(), $request->file('evidence', []));
        } catch (WorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('records.show', $record)
            ->with('success', "Report {$record->reference_no} submitted. It is now pending review.");
    }

    public function show(Request $request, ViolationRecord $record, OffenseService $offenses): View
    {
        Gate::authorize('view', $record);

        $user = $request->user();
        $record->load(['student.course', 'student.section', 'category', 'reporter', 'reviewer', 'evidence', 'appeals.filer', 'appeals.decider']);

        // Admin preview of what approval WOULD assign (informational; real numbering happens in a locked transaction).
        $preview = null;
        if ($user->isAdmin() && $record->status === ViolationRecord::PENDING) {
            $number = $offenses->nextOffenseNumber($record->student_id, $record->violation_category_id);
            $preview = ['number' => $number, 'rule' => $offenses->resolveRule($record->violation_category_id, $number)];
        }

        $history = $user->isAdmin()
            ? AuditLog::with('user')
                ->where('auditable_type', $record->getMorphClass())
                ->where('auditable_id', $record->id)
                ->latest()->latest('id')->get()
            : collect();

        return view('records.show', compact('record', 'preview', 'history'));
    }

    public function edit(ViolationRecord $record): View
    {
        Gate::authorize('update', $record);

        return view('records.form', [
            'record' => $record->load(['student.course', 'evidence']),
            'student' => $record->student,
            'categories' => ViolationCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(ViolationReportRequest $request, ViolationRecord $record, ViolationWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $record);

        try {
            $workflow->resubmit($request->user(), $record, $request->validated(), $request->file('evidence', []));
        } catch (WorkflowException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('records.show', $record)->with('success', 'Report resubmitted for review.');
    }
}
