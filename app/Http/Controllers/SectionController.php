<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Section;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function store(Request $request, Course $course, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'year_level' => ['required', 'integer', 'between:1,6'],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where('course_id', $course->id)
                    ->where('year_level', $request->input('year_level')),
            ],
        ]);

        $section = $course->sections()->create($data);
        $audit->log('section.created', $section, null, $data + ['course_id' => $course->id]);

        return back()->with('success', 'Section added.');
    }

    public function destroy(Section $section, AuditLogger $audit): RedirectResponse
    {
        // Students (even archived ones) keep pointing at their section, so it cannot be removed.
        if ($section->students()->withTrashed()->exists()) {
            return back()->with('error', 'This section still has students assigned to it and cannot be deleted.');
        }

        $audit->log('section.deleted', $section, $section->only(['course_id', 'year_level', 'name']));
        $section->delete();

        return back()->with('success', 'Section deleted.');
    }
}
