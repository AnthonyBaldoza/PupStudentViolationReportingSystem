@extends('layouts.app')

@section('title', 'Courses & Sections')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        @forelse($courses as $course)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ $course->code }} — {{ $course->name }}
                        @unless($course->is_active) <span class="badge text-bg-secondary">inactive</span> @endunless
                    </span>
                    <span>
                        <span class="text-muted small me-2">{{ $course->students_count }} student(s)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCourse{{ $course->id }}">Edit</button>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        @forelse($course->sections as $s)
                            <span class="d-inline-flex align-items-center border rounded px-2 py-1 me-1 mb-1">
                                Y{{ $s->year_level }} · {{ $s->name }} <span class="text-muted small ms-1">({{ $s->students_count }})</span>
                                <form method="POST" action="{{ route('sections.destroy', $s) }}" class="ms-2" data-confirm="Delete section {{ $s->name }}?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm p-0 border-0 text-danger" aria-label="Delete section {{ $s->name }}"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </span>
                        @empty
                            <span class="text-muted">No sections yet.</span>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('sections.store', $course) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-5 col-md-3">
                            <label class="form-label" for="sec_year_{{ $course->id }}">Year level</label>
                            <select class="form-select" name="year_level" id="sec_year_{{ $course->id }}" required>
                                @foreach(range(1, 6) as $y)<option value="{{ $y }}">Year {{ $y }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-7 col-md-5">
                            <label class="form-label" for="sec_name_{{ $course->id }}">Section name</label>
                            <input class="form-control" name="name" id="sec_name_{{ $course->id }}" maxlength="50" required placeholder="e.g. A">
                        </div>
                        <div class="col-12 col-md-4"><button class="btn btn-outline-primary w-100">Add section</button></div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="editCourse{{ $course->id }}" tabindex="-1" aria-labelledby="editCourseLabel{{ $course->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('courses.update', $course) }}" class="modal-content">
                        @csrf @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="editCourseLabel{{ $course->id }}">Edit course</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <x-field name="code" label="Code" :value="$course->code" :required="true" maxlength="30" id="edit_code_{{ $course->id }}" />
                            <x-field name="name" label="Name" :value="$course->name" :required="true" maxlength="255" id="edit_cname_{{ $course->id }}" />
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_active_{{ $course->id }}" @checked($course->is_active)>
                                <label class="form-check-label" for="edit_active_{{ $course->id }}">Active (available when registering students)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="alert alert-info">No courses yet. Add your first course on the right, then its sections.</div>
        @endforelse
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add course</div>
            <div class="card-body">
                <form method="POST" action="{{ route('courses.store') }}">
                    @csrf
                    <x-field name="code" label="Code" :required="true" maxlength="30" id="new_code" help="Short code, e.g. the program abbreviation." />
                    <x-field name="name" label="Name" :required="true" maxlength="255" id="new_cname" />
                    <button class="btn btn-primary">Add course</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
