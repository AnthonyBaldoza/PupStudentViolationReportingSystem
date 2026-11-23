{{-- Summary statistics block. Expects $summary. Plain markup so it works on screen, in print and in PDF. --}}
<table style="width:100%; border-collapse: collapse; margin-bottom: 12px;" cellpadding="4">
    <tr>
        <td style="border:1px solid #999;"><strong>Period</strong><br>{{ $summary['range'] }}</td>
        <td style="border:1px solid #999;"><strong>Total reports</strong><br>{{ $summary['total'] }}</td>
        @foreach(['pending', 'approved', 'rejected', 'returned', 'voided'] as $st)
            <td style="border:1px solid #999;"><strong>{{ ucfirst($st) }}</strong><br>{{ (int) ($summary['by_status'][$st] ?? 0) }}</td>
        @endforeach
    </tr>
</table>
@if($summary['top_categories']->isNotEmpty())
    <p style="margin: 0 0 12px 0;"><strong>Most common categories:</strong>
        @foreach($summary['top_categories'] as $name => $total){{ $name }} ({{ $total }})@unless($loop->last), @endunless @endforeach
    </p>
@endif
