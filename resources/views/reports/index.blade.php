@extends('layouts.app')

@section('title', 'Reports & Statistics')

@section('content')
@php $query = request()->query(); @endphp

<form method="GET" class="filter-bar">
    @include('reports._filters')
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply filters</button>
        <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">Clear</a>
        <span class="vr d-none d-md-block"></span>
        <a class="btn btn-outline-primary" href="{{ route('reports.print', $query) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
        <a class="btn btn-outline-primary" href="{{ route('reports.csv', $query) }}"><i class="bi bi-filetype-csv me-1"></i>Excel / CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('reports.pdf', $query) }}"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card"><div class="card-body"><div class="stat-value">{{ $summary['total'] }}</div><div class="stat-label">Reports</div></div></div></div>
    @foreach(['pending', 'approved', 'rejected', 'returned', 'voided'] as $st)
        <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card"><div class="card-body"><div class="stat-value">{{ (int) ($summary['by_status'][$st] ?? 0) }}</div><div class="stat-label">{{ ucfirst($st) }}</div></div></div></div>
    @endforeach
</div>

<p class="text-muted small">Period: <strong>{{ $summary['range'] }}</strong>
    @if($summary['top_categories']->isNotEmpty())
        · Top categories:
        @foreach($summary['top_categories'] as $name => $total){{ $name }} ({{ $total }})@unless($loop->last), @endunless @endforeach
    @endif
</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Reference</th><th>Student</th><th>Course / Yr</th><th>Category</th><th>Incident</th><th>Offense</th><th>Action</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($records as $r)
                <tr>
                    <td><a href="{{ route('records.show', $r) }}">{{ $r->reference_no }}</a></td>
                    <td>{{ $r->student?->full_name }}<div class="small text-muted">{{ $r->student?->student_no }}</div></td>
                    <td>{{ $r->student?->course?->code }} / {{ $r->student?->year_level }}</td>
                    <td>{{ $r->category_name }}</td>
                    <td>{{ $r->incident_date->format('M d, Y') }}</td>
                    <td>{{ $r->offense_label ?? '—' }}</td>
                    <td>{{ $r->action_name ?? '—' }}</td>
                    <td><x-status-badge :status="$r->status" /></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No records match the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="card-footer bg-white">{{ $records->links() }}</div>@endif
</div>
@endsection
