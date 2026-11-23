@extends('layouts.app')

@section('title', 'Violation Categories')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Categories and penalties are entirely configurable. Nothing here is preloaded from any school policy.</p>
    <a href="{{ route('categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New category</a>
</div>

@if($categories->isEmpty())
    <div class="alert alert-info">
        No categories yet. Start by adding <a href="{{ route('actions.index') }}">disciplinary actions</a>, then create a category and its offense rules from your official handbook.
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Category</th><th class="text-center">Rules</th><th class="text-center">Reports</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach($categories as $c)
                <tr>
                    <td>{{ $c->name }}@if($c->description)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($c->description, 90) }}</div>@endif</td>
                    <td class="text-center">{{ $c->rules_count }}</td>
                    <td class="text-center">{{ $c->records_count }}</td>
                    <td><x-status-badge :status="$c->is_active ? 'active' : 'inactive'" /></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('categories.edit', $c) }}" class="btn btn-sm btn-outline-primary">Edit &amp; rules</a>
                        <form method="POST" action="{{ route('categories.toggle', $c) }}" class="d-inline"
                              data-confirm="{{ $c->is_active ? 'Deactivate this category? New reports cannot use it, but all history is kept.' : 'Activate this category?' }}">
                            @csrf
                            <button class="btn btn-sm {{ $c->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">{{ $c->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
