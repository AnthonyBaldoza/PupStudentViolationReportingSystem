<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Violation Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        .meta { color: #555; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>{{ $appSettings['school_name'] ?? '' }}</h1>
    <div>{{ $appSettings['council_name'] ?? '' }}: Student Violation Report</div>
    <div class="meta">
        Generated {{ now()->format('F d, Y g:i A') }} by {{ auth()->user()->name }}
        @if($categoryName) · Category: {{ $categoryName }} @endif
        @if($courseCode) · Course: {{ $courseCode }} @endif
        @if(!empty($filters['year_level'])) · Year {{ $filters['year_level'] }} @endif
        @if(!empty($filters['status'])) · Status: {{ ucfirst($filters['status']) }} @endif
        @if(!empty($filters['offense_number'])) · Offense no. {{ $filters['offense_number'] }} @endif
    </div>

    @include('reports._summary')
    @include('reports._table')

    <p class="meta">{{ $appSettings['report_footer'] ?? '' }}</p>
</body>
</html>
