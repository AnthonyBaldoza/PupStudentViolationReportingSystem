@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Violation Reports' : 'My Reports')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ $records->total() }} report(s)</p>
    @unless($isAdmin)
        <a href="{{ route('records.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New report</a>
    @endunless
</div>

<form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label for="q" class="form-label">Student</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Student ID or name">
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                @foreach(\App\Models\ViolationRecord::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="category_id" class="form-label">Category</label>
            <select id="category_id" name="category_id" class="form-select">
                <option value="">All</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(($filters['category_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="date_from" class="form-label">From</label>
            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to" class="form-label">To</label>
            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('records.index') }}">Clear</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th><th>Student</th><th>Category</th><th>Incident</th>
                    @if($isAdmin)<th>Reported by</th>@endif
                    <th>Offense</th><th>Status</th><th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($records as $r)
                <tr>
                    <td><a href="{{ route('records.show', $r) }}">{{ $r->reference_no }}</a></td>
                    <td>{{ $r->student?->full_name }}<div class="small text-muted">{{ $r->student?->student_no }}</div></td>
                    <td>{{ $r->category_name }}</td>
                    <td>{{ $r->incident_date->format('M d, Y') }}</td>
                    @if($isAdmin)<td>{{ $r->reporter?->name }}</td>@endif
                    <td>{{ $r->offense_label ?? '—' }}</td>
                    <td><x-status-badge :status="$r->status" /></td>
                    <td class="text-end">
                        <a href="{{ route('records.show', $r) }}" class="btn btn-sm btn-outline-primary">{{ $isAdmin && $r->status === 'pending' ? 'Review' : 'View' }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $isAdmin ? 8 : 7 }}" class="text-center text-muted py-4">No reports match your filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
        <div class="card-footer bg-white">{{ $records->links() }}</div>
    @endif
</div>
@endsection
