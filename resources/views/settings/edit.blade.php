@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">School &amp; data privacy settings</div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf @method('PUT')
                    <x-field name="school_name" label="School name" :value="$settings['school_name']" :required="true" maxlength="255" />
                    <x-field name="council_name" label="Student Council name" :value="$settings['council_name']" :required="true" maxlength="255" />
                    <x-field name="report_footer" label="Report / page footer" :value="$settings['report_footer']" maxlength="500" help="Shown at the bottom of pages and printed reports." />
                    <x-field name="dpo_contact" label="Data Protection Officer contact" :value="$settings['dpo_contact']" maxlength="255"
                             help="Name and email/phone of the school's DPO, shown on the sign-in page and footer." />
                    <x-textarea name="retention_policy" label="Data retention policy (reference text)" :value="$settings['retention_policy']" rows="5" maxlength="3000"
                                help="Record how long disciplinary records are kept, per your school's policy and the Data Privacy Act of 2012 (RA 10173). This is a reference note; it does not delete anything automatically." />
                    <button class="btn btn-primary">Save settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
