{{-- Shared filter inputs for the report screen. Expects $filters, $categories, $courses. --}}
<div class="row g-2 align-items-end">
    <div class="col-6 col-md-3 col-xl-2">
        <label for="date_from" class="form-label">Date from</label>
        <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
        <label for="date_to" class="form-label">Date to</label>
        <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <label for="q" class="form-label">Student</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Student ID or name">
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <label for="category_id" class="form-label">Category</label>
        <select id="category_id" name="category_id" class="form-select">
            <option value="">All</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" @selected(($filters['category_id'] ?? null) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <label for="course_id" class="form-label">Course</label>
        <select id="course_id" name="course_id" class="form-select">
            <option value="">All</option>
            @foreach($courses as $c)
                <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-4 col-md-2">
        <label for="year_level" class="form-label">Year level</label>
        <select id="year_level" name="year_level" class="form-select">
            <option value="">All</option>
            @foreach(range(1, 6) as $y)
                <option value="{{ $y }}" @selected(($filters['year_level'] ?? null) == $y)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-4 col-md-3">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All</option>
            @foreach(\App\Models\ViolationRecord::STATUSES as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-4 col-md-2">
        <label for="offense_number" class="form-label">Offense no.</label>
        <input type="number" min="1" max="99" id="offense_number" name="offense_number" value="{{ $filters['offense_number'] ?? '' }}" class="form-control">
    </div>
</div>
