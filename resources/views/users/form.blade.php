@extends('layouts.app')

@php $editing = $user->exists; @endphp
@section('title', $editing ? 'Edit account' : 'New account')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">{{ $editing ? 'Edit '.$user->name : 'New account' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}">
                    @csrf
                    @if($editing) @method('PUT') @endif

                    <x-field name="name" label="Full name" :value="$user->name" :required="true" maxlength="255" />
                    <x-field name="email" type="email" label="Email (used to sign in)" :value="$user->email" :required="true" maxlength="255" />
                    <x-field name="position" label="Position" :value="$user->position" maxlength="100" help="e.g. the officer's title in the council." />

                    <x-select name="role" label="Role" :required="true" :disabled="$editing && $user->is(auth()->user())"
                              help="Officers file reports. Administrators review, configure and manage everything.">
                        <option value="officer" @selected(old('role', $user->role) === 'officer')>Student Council Officer</option>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrator</option>
                    </x-select>

                    @if($editing && $user->is(auth()->user()))
                        {{-- A disabled select is not submitted; the controller ignores role changes on your own account anyway. --}}
                        <input type="hidden" name="role" value="{{ $user->role }}">
                    @endif

                    @unless($editing)
                        <p class="small text-muted">A temporary password is generated and shown once after saving. The user must change it on first sign-in.</p>
                    @endunless

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create account' }}</button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
