<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\ViolationRecord;
use App\Services\AuditLogger;
use App\Services\OffenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'course_id' => ['nullable', 'integer'],
            'year_level' => ['nullable', 'integer', 'between:1,6'],
            'status' => ['nullable', Rule::in(Student::STATUSES)],
            'archived' => ['nullable', 'boolean'],
        ]);

        $query = Student::query()->with(['course', 'section']);

        if ($request->user()->isAdmin() && $request->boolean('archived')) {
            $query->onlyTrashed();
        }

        $students = $query
            ->search($filters['q'] ?? null)
            ->when($filters['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->when($filters['year_level'] ?? null, fn ($q, $v) => $q->where('year_level', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'courses' => Course::orderBy('code')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('students.form', $this->formData(new Student(['status' => Student::ACTIVE])));
    }

    public function store(StudentRequest $request, AuditLogger $audit): RedirectResponse
    {
        $student = Student::create($request->validated());

        $audit->log('student.created', $student, null, $student->only(['student_no', 'last_name', 'first_name', 'course_id', 'year_level']));

        return redirect()->route('students.show', $student)->with('success', 'Student registered.');
    }

    public function show(Request $request, Student $student, OffenseService $offenses): View
    {
        $user = $request->user();

        $records = $student->records()
            ->with(['category', 'reporter'])
            ->latest('incident_date')
            ->latest('id');

        // Officers only see verified (approved) history plus the reports they filed themselves.
        if (! $user->isAdmin()) {
            $records->where(function ($q) use ($user) {
                $q->where('status', ViolationRecord::APPROVED)->orWhere('reported_by', $user->id);
            });
        }

        return view('students.show', [
            'student' => $student->load(['course', 'section']),
            'records' => $records->paginate(10),
            'summary' => $offenses->summaryForStudent($student),
        ]);
    }

    public function edit(Student $student): View
    {
        return view('students.form', $this->formData($student));
    }

    public function update(StudentRequest $request, Student $student, AuditLogger $audit): RedirectResponse
    {
        $student->fill($request->validated());
        $audit->saveWithAudit($student, 'student.updated');

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }

    public function archive(Student $student, AuditLogger $audit): RedirectResponse
    {
        $student->delete(); // soft delete: history is preserved
        $audit->log('student.archived', $student);

        return redirect()->route('students.index')->with('success', 'Student archived. Their violation history is preserved.');
    }

    public function restore(Student $student, AuditLogger $audit): RedirectResponse
    {
        $student->restore();
        $audit->log('student.restored', $student);

        return redirect()->route('students.show', $student)->with('success', 'Student restored.');
    }

    /** JSON typeahead used by the "new report" form. Returns the minimum fields needed to identify a student. */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $students = Student::query()
            ->with('course')
            ->where('status', Student::ACTIVE)
            ->search($term)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get();

        return response()->json($students->map(fn (Student $s) => [
            'id' => $s->id,
            'student_no' => $s->student_no,
            'name' => $s->full_name,
            'course' => $s->course?->code,
            'year_level' => $s->year_level,
        ])->values());
    }

    private function formData(Student $student): array
    {
        return [
            'student' => $student,
            'courses' => Course::where('is_active', true)->orWhere('id', $student->course_id)->orderBy('code')->get(),
            'sections' => Section::orderBy('year_level')->orderBy('name')->get(),
        ];
    }
}
