@extends('layouts.app')

@section('title', 'Disciplinary Actions')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Configured actions</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Action</th><th class="text-center">Used by rules</th><th>Status</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    @forelse($actions as $a)
                        <tr>
                            <td>{{ $a->name }}@if($a->description)<div class="small text-muted pre-line">{{ $a->description }}</div>@endif</td>
                            <td class="text-center">{{ $a->rules_count }}</td>
                            <td><x-status-badge :status="$a->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAction{{ $a->id }}">Edit</button>
                                <form method="POST" action="{{ route('actions.toggle', $a) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm {{ $a->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">{{ $a->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No disciplinary actions yet. Add the penalties from your official handbook.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add disciplinary action</div>
            <div class="card-body">
                <form method="POST" action="{{ route('actions.store') }}">
                    @csrf
                    <x-field name="name" label="Name" :required="true" maxlength="255" id="new_action_name" />
                    <x-textarea name="description" label="Description" rows="3" maxlength="2000" id="new_action_desc" />
                    <button class="btn btn-primary">Add action</button>
                </form>
            </div>
        </div>
    </div>
</div>

@foreach($actions as $a)
    <div class="modal fade" id="editAction{{ $a->id }}" tabindex="-1" aria-labelledby="editActionLabel{{ $a->id }}" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('actions.update', $a) }}" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h2 class="modal-title h5" id="editActionLabel{{ $a->id }}">Edit action</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <x-field name="name" label="Name" :value="$a->name" :required="true" maxlength="255" id="edit_action_name_{{ $a->id }}" />
                    <x-textarea name="description" label="Description" :value="$a->description" rows="3" maxlength="2000" id="edit_action_desc_{{ $a->id }}" />
                    <p class="small text-muted mb-0">Approved records keep the wording they were approved with.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
