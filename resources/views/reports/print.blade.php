<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Violation Report · {{ $appSettings['council_name'] ?? '' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/svms.css') }}" rel="stylesheet">
</head>
<body>
<div class="print-sheet">
    <div class="no-print mb-3 d-flex gap-2">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">Close</button>
    </div>

    <h1 class="h4 mb-0">{{ $appSettings['school_name'] ?? '' }}</h1>
    <div class="mb-1">{{ $appSettings['council_name'] ?? '' }}: Student Violation Report</div>
    <div class="small text-muted mb-3">
        Generated {{ now()->format('F d, Y g:i A') }} by {{ auth()->user()->name }}
        @if($categoryName) · Category: {{ $categoryName }} @endif
        @if($courseCode) · Course: {{ $courseCode }} @endif
        @if(!empty($filters['year_level'])) · Year {{ $filters['year_level'] }} @endif
        @if(!empty($filters['status'])) · Status: {{ ucfirst($filters['status']) }} @endif
        @if(!empty($filters['offense_number'])) · Offense no. {{ $filters['offense_number'] }} @endif
        @if(!empty($filters['q'])) · Student: “{{ $filters['q'] }}” @endif
    </div>

    @include('reports._summary')
    @include('reports._table')

    <p class="small text-muted mt-3">{{ $appSettings['report_footer'] ?? '' }}</p>
</div>
</body>
</html>
