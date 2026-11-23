@extends('layouts.app')

@php $editing = $category->exists; @endphp
@section('title', $editing ? 'Edit category' : 'New category')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Category details</div>
            <div class="card-body">
                <form method="POST" action="{{ $editing ? route('categories.update', $category) : route('categories.store') }}">
                    @csrf
                    @if($editing) @method('PUT') @endif
                    <x-field name="name" label="Name" :value="$category->name" :required="true" maxlength="255" />
                    <x-textarea name="description" label="Description" :value="$category->description" rows="4" maxlength="2000" />
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create category' }}</button>
                        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Back</a>
                    </div>
                </form>
                @if($editing)
                    <p class="small text-muted mt-3 mb-0">Renaming a category does not change the name already stored on approved records.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @if(!$editing)
            <div class="card"><div class="card-body text-muted">Save the category first, then you can configure its 1st, 2nd, 3rd and succeeding offense rules.</div></div>
        @else
            <div class="card mb-3">
                <div class="card-header">Offense progression</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Offense</th><th>Disciplinary action</th><th>Note</th><th>Status</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        @forelse($category->rules as $rule)
                            <tr>
                                <td class="text-nowrap">{{ $rule->label }}</td>
                                <td>{{ $rule->action?->name }}</td>
                                <td class="small text-muted">{{ \Illuminate\Support\Str::limit($rule->description, 60) }}</td>
                                <td><x-status-badge :status="$rule->is_active ? 'active' : 'inactive'" /></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRule{{ $rule->id }}">Edit</button>
                                    <form method="POST" action="{{ route('rules.toggle', $rule) }}" class="d-inline">@csrf
                                        <button class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}">{{ $rule->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No rules yet. Reports in this category cannot be approved until at least a 1st-offense rule exists.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Changing a rule only affects <strong>future</strong> approvals. Offense numbers and actions already assigned are preserved on each record.
                </div>
            </div>

            <div class="card">
                <div class="card-header">Add offense rule</div>
                <div class="card-body">
                    @if($actions->isEmpty())
                        <div class="alert alert-warning mb-0">Add at least one <a href="{{ route('actions.index') }}">disciplinary action</a> first.</div>
                    @else
                        <form method="POST" action="{{ route('rules.store', $category) }}">
                            @csrf
                            <div class="row">
                                <x-field class="col-md-4" type="number" name="offense_number" label="Offense number" :required="true" min="1" max="99" id="new_offense_number"
                                         help="1 = first offense, 2 = second…" />
                                <x-select class="col-md-8" name="disciplinary_action_id" label="Disciplinary action" :required="true" id="new_action">
                                    <option value="">Select…</option>
                                    @foreach($actions as $a)
                                        <option value="{{ $a->id }}" @selected(old('disciplinary_action_id') == $a->id)>{{ $a->name }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                            <x-textarea name="description" label="Rule description / note" rows="2" maxlength="1000" id="new_rule_description" />
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_succeeding" id="new_succeeding" value="1" @checked(old('is_succeeding'))>
                                <label class="form-check-label" for="new_succeeding">Applies to this offense number <em>and all succeeding offenses</em> (e.g. “4th and succeeding”)</label>
                            </div>
                            <button class="btn btn-primary">Add rule</button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Edit modals --}}
            @foreach($category->rules as $rule)
                <div class="modal fade" id="editRule{{ $rule->id }}" tabindex="-1" aria-labelledby="editRuleLabel{{ $rule->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="POST" action="{{ route('rules.update', $rule) }}" class="modal-content">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="editRuleLabel{{ $rule->id }}">Edit {{ $rule->label }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <x-field type="number" name="offense_number" label="Offense number" :value="$rule->offense_number" :required="true" min="1" max="99" id="edit_number_{{ $rule->id }}" />
                                <x-select name="disciplinary_action_id" label="Disciplinary action" :required="true" id="edit_action_{{ $rule->id }}">
                                    @foreach($actions->concat($rule->action && !$actions->contains('id', $rule->action->id) ? [$rule->action] : []) as $a)
                                        <option value="{{ $a->id }}" @selected($rule->disciplinary_action_id == $a->id)>{{ $a->name }}</option>
                                    @endforeach
                                </x-select>
                                <x-textarea name="description" label="Rule description / note" :value="$rule->description" rows="2" maxlength="1000" id="edit_desc_{{ $rule->id }}" />
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_succeeding" id="edit_succ_{{ $rule->id }}" value="1" @checked($rule->is_succeeding)>
                                    <label class="form-check-label" for="edit_succ_{{ $rule->id }}">…and succeeding offenses</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary">Save rule</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
