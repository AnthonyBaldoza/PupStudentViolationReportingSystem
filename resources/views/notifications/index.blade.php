@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Status updates on your reports.</p>
    <form method="POST" action="{{ route('notifications.readAll') }}">@csrf
        <button class="btn btn-outline-secondary btn-sm">Mark all as read</button>
    </form>
</div>

<div class="card">
    <ul class="list-group list-group-flush">
        @forelse($notifications as $n)
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div @class(['fw-semibold' => !$n->read_at])>{{ $n->data['title'] ?? 'Notification' }}
                        @unless($n->read_at)<span class="badge text-bg-primary ms-1">new</span>@endunless
                    </div>
                    <div class="small">{{ $n->data['message'] ?? '' }}</div>
                    <div class="small text-muted">{{ $n->created_at->format('M d, Y g:i A') }}</div>
                </div>
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">@csrf
                    <button class="btn btn-sm btn-outline-primary">Open</button>
                </form>
            </li>
        @empty
            <li class="list-group-item text-center text-muted py-4">No notifications yet.</li>
        @endforelse
    </ul>
    @if($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif
</div>
@endsection
