@extends('layouts.app')

@php $editing = $record->exists; @endphp
@section('title', $editing ? 'Update returned report' : 'New violation report')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        @if($editing && $record->review_remarks)
            <div class="alert alert-info">
                <strong>The administrator asked for more information:</strong>
                <div class="pre-line mt-1">{{ $record->review_remarks }}</div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">{{ $editing ? 'Update '.$record->reference_no : 'Report details' }}</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data"
                      action="{{ $editing ? route('records.update', $record) : route('records.store') }}">
                    @csrf
                    @if($editing) @method('PUT') @endif

                    {{-- 1. Student --}}
                    <div class="mb-4">
                        <label for="student_search" class="form-label">Student <span class="text-danger" aria-hidden="true">*</span></label>

                        @if($editing)
                            <div class="form-control bg-light">{{ $student?->full_name }} ({{ $student?->student_no }})</div>
                            <div class="form-text">The student cannot be changed on a returned report.</div>
                        @else
                            <input type="hidden" name="student_id" id="student_id" value="{{ old('student_id', $student?->id) }}">
                            <div id="student_selected" @class(['alert alert-secondary py-2 d-flex justify-content-between align-items-center', 'd-none' => !old('student_id', $student?->id)])>
                                <span id="student_selected_text">{{ $student ? $student->full_name.' ('.$student->student_no.')' : '' }}</span>
                                <button type="button" id="student_clear" class="btn btn-sm btn-outline-secondary">Change</button>
                            </div>
                            <div id="student_search_wrap" @class(['d-none' => old('student_id', $student?->id)])>
                                <input type="search" id="student_search" class="form-control" autocomplete="off"
                                       placeholder="Type at least 2 characters of the Student ID or name"
                                       aria-describedby="student_help" aria-controls="student_results">
                                <div id="student_results" class="list-group mt-1" role="listbox" aria-label="Matching students"></div>
                                <div id="student_help" class="form-text">Only active students can be reported.</div>
                            </div>
                            @error('student_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @endif
                    </div>

                    {{-- 2. Category --}}
                    <x-select name="violation_category_id" label="Violation category" :required="true">
                        <option value="">Select category…</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('violation_category_id', $record->violation_category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </x-select>

                    {{-- 3. Incident details --}}
                    <div class="row">
                        <x-field class="col-md-4" type="date" name="incident_date" label="Incident date" :required="true"
                                 :value="$record->incident_date?->format('Y-m-d')" max="{{ now()->toDateString() }}" />
                        <x-field class="col-md-3" type="time" name="incident_time" label="Time"
                                 :value="$record->incident_time ? substr($record->incident_time, 0, 5) : null" />
                        <x-field class="col-md-5" name="location" label="Location" :required="true" :value="$record->location" maxlength="255" />
                    </div>

                    <x-textarea name="description" label="Description of the incident" :required="true" rows="5"
                                :value="$record->description" minlength="10" maxlength="5000"
                                help="Describe what happened factually. Avoid opinions." />

                    {{-- 4. Evidence --}}
                    <div class="mb-4">
                        <label for="evidence" class="form-label">Supporting evidence <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="file" id="evidence" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                               @class(['form-control', 'is-invalid' => $errors->has('evidence') || $errors->has('evidence.*')]) aria-describedby="evidence_help">
                        <div id="evidence_help" class="form-text">Up to 5 files (JPG, PNG or PDF), 5 MB each. Files are stored privately and only authorised users can open them.</div>
                        @error('evidence')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @foreach($errors->get('evidence.*') as $messages)
                            @foreach($messages as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                        @endforeach

                        @if($editing && $record->evidence->isNotEmpty())
                            <div class="mt-2 small">Already attached:
                                @foreach($record->evidence as $e)
                                    <a href="{{ route('evidence.show', $e) }}">{{ $e->original_name }}</a>@unless($loop->last), @endunless
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $editing ? 'Resubmit for review' : 'Submit report' }}</button>
                        <a href="{{ $editing ? route('records.show', $record) : route('records.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                    <p class="small text-muted mt-3 mb-0">Reports are saved as <strong>Pending</strong>. The offense number and disciplinary action are assigned only when the administrator approves the report.</p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@unless($editing)
@push('scripts')
<script>
    (function () {
        var search = document.getElementById('student_search');
        var results = document.getElementById('student_results');
        var hidden = document.getElementById('student_id');
        var selected = document.getElementById('student_selected');
        var selectedText = document.getElementById('student_selected_text');
        var wrap = document.getElementById('student_search_wrap');
        var clear = document.getElementById('student_clear');
        var url = @json(route('students.lookup'));
        var timer = null;
        var seq = 0;

        function pick(student) {
            hidden.value = student.id;
            selectedText.textContent = student.name + ' (' + student.student_no + ')';
            selected.classList.remove('d-none');
            wrap.classList.add('d-none');
            results.textContent = '';
        }

        function render(list) {
            results.textContent = '';
            if (!list.length) {
                var none = document.createElement('div');
                none.className = 'list-group-item text-muted';
                none.textContent = 'No active student found.';
                results.appendChild(none);
                return;
            }
            list.forEach(function (s) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action';
                btn.setAttribute('role', 'option');
                // textContent only: never inject server data as HTML.
                btn.textContent = s.name + ' · ' + s.student_no + ' · ' + (s.course || '') + ' Y' + s.year_level;
                btn.addEventListener('click', function () { pick(s); });
                results.appendChild(btn);
            });
        }

        search.addEventListener('input', function () {
            window.clearTimeout(timer);
            var term = search.value.trim();
            if (term.length < 2) { results.textContent = ''; return; }
            timer = window.setTimeout(function () {
                var mine = ++seq;
                fetch(url + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(function (list) { if (mine === seq) { render(list); } })
                    .catch(function () { results.textContent = ''; });
            }, 250);
        });

        clear.addEventListener('click', function () {
            hidden.value = '';
            selected.classList.add('d-none');
            wrap.classList.remove('d-none');
            search.value = '';
            search.focus();
        });
    })();
</script>
@endpush
@endunless
