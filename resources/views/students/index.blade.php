@extends('layouts.app')

@section('title', 'Students')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ $students->total() }} student(s) found</p>
    @if($isAdmin)
        <a href="{{ route('students.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Register student</a>
    @endif
</div>

<form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label for="q" class="form-label">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Student ID or name">
        </div>
        <div class="col-6 col-md-2">
            <label for="course_id" class="form-label">Course</label>
            <select id="course_id" name="course_id" class="form-select">
                <option value="">All</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="year_level" class="form-label">Year level</label>
            <select id="year_level" name="year_level" class="form-select">
                <option value="">All</option>
                @foreach(range(1, 6) as $y)
                    <option value="{{ $y }}" @selected(($filters['year_level'] ?? null) == $y)>Year {{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                @foreach(\App\Models\Student::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('students.index') }}" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
        @if($isAdmin)
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="archived" value="1" id="archived" @checked(request()->boolean('archived'))>
                    <label class="form-check-label" for="archived">Show archived students only</label>
                </div>
            </div>
        @endif
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Student ID</th><th>Name</th><th>Course</th><th>Year</th><th>Section</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse($students as $s)
                <tr>
                    <td><a href="{{ route('students.show', $s) }}">{{ $s->student_no }}</a></td>
                    <td>{{ $s->full_name }}</td>
                    <td>{{ $s->course?->code }}</td>
                    <td>{{ $s->year_level }}</td>
                    <td>{{ $s->section?->name ?? '—' }}</td>
                    <td>
                        @if($s->trashed()) <x-status-badge status="archived" />
                        @else <x-status-badge :status="$s->status" /> @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('students.show', $s) }}" class="btn btn-sm btn-outline-primary">View</a>
                        @if($isAdmin && !$s->trashed())
                            <a href="{{ route('students.edit', $s) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        @elseif($isAdmin && $s->trashed())
                            <form method="POST" action="{{ route('students.restore', $s) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-outline-success">Restore</button>
                            </form>
                        @endif
                        @if(!$isAdmin && !$s->trashed() && $s->status === 'active')
                            <a href="{{ route('records.create', ['student' => $s->id]) }}" class="btn btn-sm btn-primary">Report</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No students match your search.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($students->hasPages())
        <div class="card-footer bg-white">{{ $students->links() }}</div>
    @endif
</div>
@endsection
