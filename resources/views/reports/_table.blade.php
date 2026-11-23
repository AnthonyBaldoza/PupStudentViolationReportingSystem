{{-- Plain report table shared by print and PDF. Expects $records. --}}
<table style="width:100%; border-collapse: collapse; font-size: 11px;" cellpadding="4">
    <thead>
        <tr style="background:#e9edf3;">
            @foreach(['Reference', 'Student ID', 'Student', 'Course / Yr', 'Category', 'Incident', 'Status', 'Offense', 'Action', 'Reported by'] as $h)
                <th style="border:1px solid #999; text-align:left;">{{ $h }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
    @forelse($records as $r)
        <tr>
            <td style="border:1px solid #999;">{{ $r->reference_no }}</td>
            <td style="border:1px solid #999;">{{ $r->student?->student_no }}</td>
            <td style="border:1px solid #999;">{{ $r->student?->full_name }}</td>
            <td style="border:1px solid #999;">{{ $r->student?->course?->code }} / {{ $r->student?->year_level }}</td>
            <td style="border:1px solid #999;">{{ $r->category_name }}</td>
            <td style="border:1px solid #999;">{{ $r->incident_date->format('Y-m-d') }}</td>
            <td style="border:1px solid #999;">{{ ucfirst($r->status) }}</td>
            <td style="border:1px solid #999;">{{ $r->offense_label ?? '—' }}</td>
            <td style="border:1px solid #999;">{{ $r->action_name ?? '—' }}</td>
            <td style="border:1px solid #999;">{{ $r->reporter?->name }}</td>
        </tr>
    @empty
        <tr><td colspan="10" style="border:1px solid #999; text-align:center;">No records match the selected filters.</td></tr>
    @endforelse
    </tbody>
</table>
