<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::query()
            ->with(['sections' => fn ($q) => $q->withCount('students')->orderBy('year_level')->orderBy('name')])
            ->withCount('students')
            ->orderBy('code')
            ->get();

        return view('courses.index', compact('courses'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_ ]+$/', 'unique:courses,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $course = Course::create($data + ['is_active' => true]);
        $audit->log('course.created', $course, null, $data);

        return back()->with('success', 'Course added.');
    }

    public function update(Request $request, Course $course, AuditLogger $audit): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_ ]+$/', Rule::unique('courses', 'code')->ignore($course->id)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $course->fill($data + ['is_active' => $request->boolean('is_active')]);
        $audit->saveWithAudit($course, 'course.updated');

        return back()->with('success', 'Course updated.');
    }
}
