@extends('layouts.app')

@section('title', 'Student profile')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Profile</span>
                @if($student->trashed()) <x-status-badge status="archived" /> @else <x-status-badge :status="$student->status" /> @endif
            </div>
            <div class="card-body">
                <h2 class="h5 mb-1">{{ $student->full_name }}</h2>
                <div class="text-muted mb-3">{{ $student->student_no }}</div>
                <dl class="row mb-0">
                    <dt class="col-5">Course</dt><dd class="col-7">{{ $student->course?->code }} <span class="text-muted small">{{ $student->course?->name }}</span></dd>
                    <dt class="col-5">Year level</dt><dd class="col-7">{{ $student->year_level }}</dd>
                    <dt class="col-5">Section</dt><dd class="col-7">{{ $student->section?->name ?? '—' }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                @if($isAdmin && !$student->trashed())
                    <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                @endif
                @if($isAdmin && $student->trashed())
                    <form method="POST" action="{{ route('students.restore', $student) }}">@csrf
                        <button class="btn btn-outline-success btn-sm">Restore student</button>
                    </form>
                @endif
                @if(!$isAdmin && !$student->trashed() && $student->status === 'active')
                    <a href="{{ route('records.create', ['student' => $student->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>File a report</a>
                @endif
                <a href="{{ route('students.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Offense standing by category <span class="fw-normal text-muted small">(approved records only)</span></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Category</th><th class="text-center">Approved</th><th class="text-center">Highest offense</th><th class="text-center">Next would be</th></tr></thead>
                    <tbody>
                    @forelse($summary as $row)
                        <tr>
                            <td>{{ $row['category']->name }}
                                @unless($row['category']->is_active) <span class="badge text-bg-secondary">inactive</span> @endunless
                            </td>
                            <td class="text-center">{{ $row['count'] }}</td>
                            <td class="text-center">{{ \App\Support\Ordinal::format($row['highest']) }}</td>
                            <td class="text-center text-muted">{{ \App\Support\Ordinal::format($row['next']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No approved violations on record.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Each category is counted separately. Pending, rejected and voided reports are never counted.</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Violation history</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Reference</th><th>Category</th><th>Incident</th><th>Offense</th><th>Disciplinary action</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($records as $r)
                <tr>
                    <td><a href="{{ route('records.show', $r) }}">{{ $r->reference_no }}</a></td>
                    <td>{{ $r->category_name }}</td>
                    <td>{{ $r->incident_date->format('M d, Y') }}</td>
                    <td>{{ $r->offense_label ?? '—' }}</td>
                    <td>{{ $r->action_name ?? '—' }}</td>
                    <td><x-status-badge :status="$r->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No violation history to show.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
        <div class="card-footer bg-white">{{ $records->links() }}</div>
    @endif
    @unless($isAdmin)
        <div class="card-footer bg-white small text-muted">You can see verified (approved) history plus the reports you filed yourself.</div>
    @endunless
</div>
@endsection
