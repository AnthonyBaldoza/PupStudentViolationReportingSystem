@extends('layouts.app')

@section('title', 'Change password')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        @if(auth()->user()->must_change_password)
            <div class="alert alert-warning">Your account uses a temporary password. Choose a new password to continue.</div>
        @endif

        <div class="card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')
                    <x-field name="current_password" label="Current password" type="password" :required="true" autocomplete="current-password" />
                    <x-field name="password" label="New password" type="password" :required="true" autocomplete="new-password"
                             help="At least 10 characters, with upper and lower case letters and a number." />
                    <x-field name="password_confirmation" label="Confirm new password" type="password" :required="true" autocomplete="new-password" />
                    <button type="submit" class="btn btn-primary">Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
