@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<form method="GET" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label for="action" class="form-label">Action starts with</label>
            <input id="action" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" placeholder="e.g. violation., auth., user.">
        </div>
        <div class="col-6 col-md-3">
            <label for="user_id" class="form-label">User</label>
            <select id="user_id" name="user_id" class="form-select">
                <option value="">Anyone</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
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
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('audit.index') }}" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('M d, Y g:i:s A') }}</td>
                    <td>{{ $log->user?->name ?? 'System / guest' }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td class="small">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                    <td class="small">
                        @if($log->old_values || $log->new_values)
                            <details>
                                <summary>View changes</summary>
                                @if($log->old_values)<div><strong>Before:</strong> <code class="text-break">{{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE) }}</code></div>@endif
                                @if($log->new_values)<div><strong>After:</strong> <code class="text-break">{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</code></div>@endif
                            </details>
                        @else — @endif
                    </td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No audit entries match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif
</div>
@endsection
