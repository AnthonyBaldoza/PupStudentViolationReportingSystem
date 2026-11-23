@props(['status'])
@php
    $map = [
        'pending' => ['warning', 'Pending'],
        'approved' => ['success', 'Approved'],
        'rejected' => ['danger', 'Rejected'],
        'returned' => ['info', 'Returned'],
        'voided' => ['secondary', 'Voided (appeal)'],
        'upheld' => ['success', 'Upheld'],
        'overturned' => ['secondary', 'Overturned'],
        'active' => ['success', 'Active'],
        'inactive' => ['secondary', 'Inactive'],
        'graduated' => ['info', 'Graduated'],
        'archived' => ['dark', 'Archived'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', ucfirst((string) $status)];
@endphp
<span class="badge text-bg-{{ $color }}">{{ $label }}</span>
