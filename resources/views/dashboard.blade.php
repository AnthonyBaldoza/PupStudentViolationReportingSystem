@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

@if(!$isAdmin)
    <p class="text-muted">Figures below cover the reports you filed. Student totals include all registered students.</p>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['Registered students', $stats['students'], 'bi-people', null],
        ['Total reports', $stats['reports'], 'bi-journal-text', null],
        ['Pending', $stats['pending'], 'bi-hourglass-split', 'pending'],
        ['Approved', $stats['approved'], 'bi-check-circle', 'approved'],
        ['Rejected', $stats['rejected'], 'bi-x-circle', 'rejected'],
        ['Returned', $stats['returned'], 'bi-arrow-return-left', 'returned'],
    ] as [$label, $value, $icon, $status])
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card stat-card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value">{{ number_format($value) }}</div>
                        <div class="stat-label">{{ $label }}</div>
                        @if($status)
                            <a class="small stretched-link" href="{{ route('records.index', ['status' => $status]) }}">View</a>
                        @endif
                    </div>
                    <i class="bi {{ $icon }} stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Monthly violation reports (last 12 months, by incident date)</div>
            <div class="card-body">
                <div style="position: relative; height: 300px;">
                    <canvas id="monthlyChart" role="img" aria-label="Bar chart of monthly violation reports by status"></canvas>
                </div>
                <noscript><p class="text-muted">Enable JavaScript to see this chart.</p></noscript>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Most common violations (approved)</div>
            <div class="card-body">
                @if(count($topCategories['labels']))
                    <div style="position: relative; height: 300px;">
                        <canvas id="categoryChart" role="img" aria-label="Chart of the most common approved violation categories"></canvas>
                    </div>
                @else
                    <p class="text-muted mb-0">No approved violations yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent violation activity</span>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('records.index') }}">All reports</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Reference</th><th>Student</th><th>Category</th><th>Incident</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($recent as $r)
                <tr>
                    <td><a href="{{ route('records.show', $r) }}">{{ $r->reference_no }}</a></td>
                    <td>{{ $r->student?->full_name }} <span class="text-muted small">({{ $r->student?->student_no }})</span></td>
                    <td>{{ $r->category_name }}</td>
                    <td>{{ $r->incident_date->format('M d, Y') }}</td>
                    <td><x-status-badge :status="$r->status" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No reports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var monthly = @json($monthly);
        var top = @json($topCategories);

        var monthlyEl = document.getElementById('monthlyChart');
        if (monthlyEl && window.Chart) {
            new Chart(monthlyEl, {
                type: 'bar',
                data: {
                    labels: monthly.labels,
                    datasets: [
                        { label: 'Approved', data: monthly.approved, backgroundColor: '#1d3d78' },
                        { label: 'Pending', data: monthly.pending, backgroundColor: '#f2b705' },
                        { label: 'Rejected', data: monthly.rejected, backgroundColor: '#9aa5b8' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        var catEl = document.getElementById('categoryChart');
        if (catEl && window.Chart && top.labels.length) {
            new Chart(catEl, {
                type: 'bar',
                data: { labels: top.labels, datasets: [{ label: 'Approved reports', data: top.values, backgroundColor: '#1d3d78' }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false } }
                }
            });
        }
    })();
</script>
@endpush
