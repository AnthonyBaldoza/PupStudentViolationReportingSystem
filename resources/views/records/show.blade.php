@extends('layouts.app')

@section('title', 'Report '.$record->reference_no)

@section('content')
@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $pendingAppeal = $record->appeals->firstWhere('status', 'pending');
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $record->reference_no }}</span>
                <x-status-badge :status="$record->status" />
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Student</dt>
                    <dd class="col-sm-8"><a href="{{ route('students.show', $record->student) }}">{{ $record->student->full_name }}</a>
                        <span class="text-muted small">({{ $record->student->student_no }})</span>
                        <div class="small text-muted">{{ $record->student->course?->code }} · Year {{ $record->student->year_level }} @if($record->student->section) · {{ $record->student->section->name }} @endif</div>
                    </dd>
                    <dt class="col-sm-4">Violation category</dt><dd class="col-sm-8">{{ $record->category_name }}</dd>
                    <dt class="col-sm-4">Incident</dt>
                    <dd class="col-sm-8">{{ $record->incident_date->format('F d, Y') }}@if($record->incident_time) at {{ \Illuminate\Support\Carbon::parse($record->incident_time)->format('g:i A') }}@endif</dd>
                    <dt class="col-sm-4">Location</dt><dd class="col-sm-8">{{ $record->location }}</dd>
                    <dt class="col-sm-4">Description</dt><dd class="col-sm-8 pre-line">{{ $record->description }}</dd>
                    <dt class="col-sm-4">Reported by</dt><dd class="col-sm-8">{{ $record->reporter?->name }} <span class="text-muted small">on {{ $record->created_at->format('M d, Y g:i A') }}</span></dd>
                </dl>
            </div>
        </div>

        {{-- Outcome (only once reviewed) --}}
        @if($record->status !== 'pending')
            <div class="card mb-3">
                <div class="card-header">Review outcome</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Reviewed by</dt><dd class="col-sm-8">{{ $record->reviewer?->name ?? '—' }} <span class="text-muted small">{{ $record->reviewed_at?->format('M d, Y g:i A') }}</span></dd>
                        @if($record->offense_number)
                            <dt class="col-sm-4">Offense number</dt><dd class="col-sm-8"><strong>{{ $record->offense_label }}</strong> <span class="text-muted small">in “{{ $record->category_name }}”</span></dd>
                            <dt class="col-sm-4">Disciplinary action</dt>
                            <dd class="col-sm-8"><strong>{{ $record->action_name }}</strong>
                                @if($record->action_description)<div class="small text-muted pre-line">{{ $record->action_description }}</div>@endif
                            </dd>
                            @if($record->rule_description)
                                <dt class="col-sm-4">Rule note</dt><dd class="col-sm-8 pre-line">{{ $record->rule_description }}</dd>
                            @endif
                        @endif
                        @if($record->review_remarks)
                            <dt class="col-sm-4">Remarks</dt><dd class="col-sm-8 pre-line">{{ $record->review_remarks }}</dd>
                        @endif
                        @if($record->status === 'voided')
                            <dt class="col-sm-4">Voided</dt>
                            <dd class="col-sm-8">{{ $record->voided_at?->format('M d, Y g:i A') }} — <span class="pre-line">{{ $record->void_reason }}</span>
                                <div class="small text-muted">This record is kept for history but no longer counts toward any offense total.</div></dd>
                        @endif
                    </dl>
                </div>
            </div>
        @endif

        {{-- Evidence --}}
        <div class="card mb-3">
            <div class="card-header">Evidence</div>
            <ul class="list-group list-group-flush">
                @forelse($record->evidence as $e)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-paperclip me-1"></i>{{ $e->original_name }} <span class="text-muted small">({{ $e->human_size }})</span></span>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('evidence.show', $e) }}">Download</a>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No evidence attached.</li>
                @endforelse
            </ul>
        </div>

        {{-- Appeals --}}
        @if($isAdmin && ($record->status === 'approved' || $record->appeals->isNotEmpty()))
            <div class="card mb-3">
                <div class="card-header">Appeals / disputes</div>
                <div class="card-body">
                    @forelse($record->appeals as $a)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>Appeal #{{ $a->id }}</strong><x-status-badge :status="$a->status" />
                            </div>
                            <div class="small text-muted mb-2">Logged by {{ $a->filer?->name }} on {{ $a->created_at->format('M d, Y g:i A') }}</div>
                            <div class="pre-line">{{ $a->statement }}</div>

                            @if($a->status === 'pending')
                                <hr>
                                <form method="POST" action="{{ route('appeals.decide', $a) }}" data-confirm="Record this decision? An overturned appeal voids the record (it stays in history).">
                                    @csrf
                                    <x-select name="decision" label="Decision" :required="true" id="decision_{{ $a->id }}">
                                        <option value="upheld">Uphold — the violation record stands</option>
                                        <option value="overturned">Overturn — void the violation record</option>
                                    </x-select>
                                    <x-textarea name="remarks" label="Reason for the decision" :required="true" rows="3" id="decision_remarks_{{ $a->id }}" />
                                    <button class="btn btn-primary btn-sm">Record decision</button>
                                </form>
                            @else
                                <div class="mt-2 small"><strong>Decided</strong> by {{ $a->decider?->name }} on {{ $a->decided_at?->format('M d, Y g:i A') }}:
                                    <span class="pre-line">{{ $a->decision_remarks }}</span></div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted">No appeals have been logged for this record.</p>
                    @endforelse

                    @if($record->status === 'approved' && !$pendingAppeal)
                        <form method="POST" action="{{ route('appeals.store', $record) }}">
                            @csrf
                            <x-textarea name="statement" label="Log an appeal on behalf of the student" :required="true" rows="3" minlength="10" id="appeal_statement"
                                        help="Record the student's statement. The original record stays unchanged until a decision is made." />
                            <button class="btn btn-outline-primary btn-sm">Log appeal</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Audit trail (admin) --}}
        @if($isAdmin && $history->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">Change history</div>
                <ul class="list-group list-group-flush">
                    @foreach($history as $h)
                        <li class="list-group-item small">
                            <strong>{{ $h->action }}</strong> by {{ $h->user?->name ?? 'system' }}
                            <span class="text-muted">· {{ $h->created_at->format('M d, Y g:i A') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Side column --}}
    <div class="col-lg-4">
        @if($record->status === 'returned' && $user->can('update', $record))
            <div class="card mb-3 border-info">
                <div class="card-header">Action needed</div>
                <div class="card-body">
                    <p>The administrator asked for more information.</p>
                    <a href="{{ route('records.edit', $record) }}" class="btn btn-primary w-100">Update and resubmit</a>
                </div>
            </div>
        @endif

        @if($isAdmin && $record->status === 'pending')
            <div class="card mb-3 border-primary">
                <div class="card-header">Review this report</div>
                <div class="card-body">
                    <div class="alert alert-light border small">
                        <strong>If approved now:</strong><br>
                        This would be the student's <strong>{{ \App\Support\Ordinal::format($preview['number']) }}</strong> offense in “{{ $record->category_name }}”.<br>
                        @if($preview['rule'])
                            Action: <strong>{{ $preview['rule']->action->name }}</strong>
                        @else
                            <span class="text-danger">No active rule is configured for this offense number. <a href="{{ route('categories.edit', $record->violation_category_id) }}">Configure it</a> before approving.</span>
                        @endif
                    </div>

                    @if($record->reported_by === $user->id)
                        <div class="alert alert-warning small mb-0">You filed this report, so you cannot review it.</div>
                    @else
                        <form method="POST" action="{{ route('records.approve', $record) }}" class="mb-3" data-confirm="Approve this report? The offense number and action will be assigned and recorded.">
                            @csrf
                            <x-textarea name="remarks" label="Remarks (optional)" rows="2" id="approve_remarks" />
                            <button class="btn btn-success w-100" @disabled(!$preview['rule'])><i class="bi bi-check-lg me-1"></i>Approve</button>
                        </form>

                        <hr>
                        <form method="POST" action="{{ route('records.return', $record) }}" class="mb-3">
                            @csrf
                            <label for="return_remarks" class="form-label">Request more information</label>
                            <textarea id="return_remarks" name="remarks" rows="2" class="form-control mb-2" required placeholder="What does the officer need to add or fix?"></textarea>
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-return-left me-1"></i>Return to officer</button>
                        </form>

                        <form method="POST" action="{{ route('records.reject', $record) }}" data-confirm="Reject this report? It will not count toward any offense total.">
                            @csrf
                            <label for="reject_remarks" class="form-label">Reject</label>
                            <textarea id="reject_remarks" name="remarks" rows="2" class="form-control mb-2" required placeholder="Reason for rejection"></textarea>
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <a href="{{ route('records.index') }}" class="btn btn-outline-secondary w-100">Back to reports</a>
    </div>
</div>
@endsection
