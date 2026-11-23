@extends('layouts.app')

@php $editing = $student->exists; @endphp
@section('title', $editing ? 'Edit student' : 'Register student')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">{{ $editing ? 'Edit '.$student->full_name : 'New student' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $editing ? route('students.update', $student) : route('students.store') }}">
                    @csrf
                    @if($editing) @method('PUT') @endif

                    <div class="row">
                        <x-field class="col-md-6" name="student_no" label="Student ID" :value="$student->student_no" :required="true" maxlength="30"
                                 help="Letters, numbers and dashes. Must be unique." />
                        <x-select class="col-md-6" name="status" label="Status" :required="true">
                            @foreach(\App\Models\Student::STATUSES as $s)
                                <option value="{{ $s }}" @selected(old('status', $student->status) === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="row">
                        <x-field class="col-md-4" name="last_name" label="Last name" :value="$student->last_name" :required="true" maxlength="100" />
                        <x-field class="col-md-4" name="first_name" label="First name" :value="$student->first_name" :required="true" maxlength="100" />
                        <x-field class="col-md-4" name="middle_name" label="Middle name" :value="$student->middle_name" maxlength="100" />
                    </div>
                    <div class="row">
                        <x-select class="col-md-4" name="course_id" label="Course" :required="true" id="course_id">
                            <option value="">Select course…</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}" @selected(old('course_id', $student->course_id) == $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </x-select>
                        <x-select class="col-md-4" name="year_level" label="Year level" :required="true">
                            <option value="">Select…</option>
                            @foreach(range(1, 6) as $y)
                                <option value="{{ $y }}" @selected(old('year_level', $student->year_level) == $y)>Year {{ $y }}</option>
                            @endforeach
                        </x-select>
                        <x-select class="col-md-4" name="section_id" label="Section" id="section_id">
                            <option value="">None</option>
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}" data-course="{{ $sec->course_id }}" data-year="{{ $sec->year_level }}"
                                        @selected(old('section_id', $student->section_id) == $sec->id)>{{ $sec->name }} (Y{{ $sec->year_level }})</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Register student' }}</button>
                        <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

                @if($editing)
                    <hr class="my-4">
                    <form method="POST" action="{{ route('students.archive', $student) }}"
                          data-confirm="Archive this student? They will be hidden from searches and new reports, but their violation history is kept.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-archive me-1"></i>Archive student</button>
                        <span class="text-muted small ms-2">Records are never deleted.</span>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Only offer sections that belong to the chosen course and year level.
    (function () {
        var course = document.getElementById('course_id');
        var year = document.getElementById('year_level');
        var section = document.getElementById('section_id');
        if (!course || !section || !year) { return; }

        function filter() {
            Array.prototype.forEach.call(section.options, function (opt) {
                if (!opt.value) { return; }
                var ok = (!course.value || opt.dataset.course === course.value) && (!year.value || opt.dataset.year === year.value);
                opt.hidden = !ok;
                opt.disabled = !ok;
                if (!ok && opt.selected) { section.value = ''; }
            });
        }
        course.addEventListener('change', filter);
        year.addEventListener('change', filter);
        filter();
    })();
</script>
@endpush
