@extends('layouts.app')

@section('title', 'Officer Accounts')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Create and manage Student Council officer and administrator accounts.</p>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New account</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td>{{ $u->name }}@if($u->position)<div class="small text-muted">{{ $u->position }}</div>@endif</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->isAdmin() ? 'Administrator' : 'Officer' }}</td>
                    <td class="small">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td><x-status-badge :status="$u->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @if(!$u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.resetPassword', $u) }}" class="d-inline" data-confirm="Generate a new temporary password for {{ $u->name }}? Their current password will stop working.">
                                @csrf <button class="btn btn-sm btn-outline-secondary">Reset password</button>
                            </form>
                            <form method="POST" action="{{ route('users.toggle', $u) }}" class="d-inline" data-confirm="{{ $u->is_active ? 'Deactivate this account? They will be signed out and cannot sign in.' : 'Reactivate this account?' }}">
                                @csrf <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
</div>
@endsection
