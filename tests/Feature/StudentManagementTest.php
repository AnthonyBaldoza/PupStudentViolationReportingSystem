<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    private function payload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'student_no' => 'DEMO-100001',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'course_id' => $course->id,
            'section_id' => null,
            'year_level' => 2,
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_can_register_a_student(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->post(route('students.store'), $this->payload($course))
            ->assertRedirect();

        $this->assertDatabaseHas('students', ['student_no' => 'DEMO-100001', 'last_name' => 'Dela Cruz', 'course_id' => $course->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.created']);
    }

    public function test_student_id_is_normalised_and_duplicates_are_rejected(): void
    {
        $course = Course::factory()->create();
        $admin = $this->makeAdmin();
        Student::factory()->create(['student_no' => 'DEMO-100001', 'course_id' => $course->id]);

        // Different case + surrounding spaces still counts as the same ID.
        $this->actingAs($admin)
            ->post(route('students.store'), $this->payload($course, ['student_no' => '  demo-100001 ']))
            ->assertSessionHasErrors('student_no');

        $this->assertSame(1, Student::withTrashed()->where('student_no', 'DEMO-100001')->count());
    }

    public function test_an_archived_students_id_cannot_be_reused(): void
    {
        $course = Course::factory()->create();
        $admin = $this->makeAdmin();
        $old = Student::factory()->create(['student_no' => 'DEMO-100001', 'course_id' => $course->id]);
        $old->delete();

        $this->actingAs($admin)
            ->post(route('students.store'), $this->payload($course))
            ->assertSessionHasErrors('student_no');
    }

    public function test_required_fields_and_formats_are_validated(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->post(route('students.store'), $this->payload($course, [
                'student_no' => 'bad id!',
                'last_name' => '',
                'year_level' => 9,
                'status' => 'banana',
                'course_id' => 99999,
            ]))
            ->assertSessionHasErrors(['student_no', 'last_name', 'year_level', 'status', 'course_id']);
    }

    public function test_a_section_must_belong_to_the_selected_course(): void
    {
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();
        $foreignSection = Section::factory()->create(['course_id' => $otherCourse->id]);

        $this->actingAs($this->makeAdmin())
            ->post(route('students.store'), $this->payload($course, ['section_id' => $foreignSection->id]))
            ->assertSessionHasErrors('section_id');
    }

    public function test_admin_can_update_a_student_and_the_change_is_audited(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create(['student_no' => 'DEMO-100001', 'course_id' => $course->id, 'year_level' => 1]);

        $this->actingAs($this->makeAdmin())
            ->put(route('students.update', $student), $this->payload($course, ['year_level' => 3]))
            ->assertRedirect(route('students.show', $student));

        $this->assertSame(3, $student->fresh()->year_level);

        $log = \App\Models\AuditLog::where('action', 'student.updated')->latest('id')->firstOrFail();
        $this->assertSame(1, $log->old_values['year_level']);
        $this->assertSame(3, $log->new_values['year_level']);
    }

    public function test_updating_a_student_may_keep_their_own_student_id(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create(['student_no' => 'DEMO-100001', 'course_id' => $course->id]);

        $this->actingAs($this->makeAdmin())
            ->put(route('students.update', $student), $this->payload($course, ['first_name' => 'Renamed']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $student->fresh()->first_name);
    }

    public function test_archiving_hides_the_student_but_keeps_their_violation_history(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);
        $record = $this->approvedRecord($officer, $admin, $student, $category);

        $this->actingAs($admin)->delete(route('students.archive', $student))->assertRedirect(route('students.index'));

        $this->assertSoftDeleted($student);
        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);
        $this->assertNotNull($record->fresh()->student); // relation still resolves for archived students

        // Archived students vanish from the default list and from the officer lookup...
        $this->actingAs($admin)->get(route('students.index'))->assertDontSee($student->student_no);
        $this->actingAs($officer)->getJson(route('students.lookup', ['q' => $student->last_name]))->assertJsonCount(0);

        // ...but the profile (with history) is still reachable by an admin, and can be restored.
        $this->actingAs($admin)->get(route('students.show', $student))->assertOk()->assertSee($record->reference_no);
        $this->actingAs($admin)->post(route('students.restore', $student))->assertRedirect();
        $this->assertNotSoftDeleted($student);
    }

    public function test_new_reports_cannot_be_filed_against_an_archived_student(): void
    {
        $officer = $this->makeOfficer();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);
        $student->delete();

        $this->actingAs($officer)->post(route('records.store'), [
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'incident_date' => now()->subDay()->toDateString(),
            'location' => 'Gate',
            'description' => 'Should be refused for an archived student.',
        ])->assertSessionHas('error');

        $this->assertSame(0, ViolationRecord::count());
    }

    public function test_students_can_be_searched_by_id_or_name_and_filtered(): void
    {
        $cs = Course::factory()->create(['code' => 'DEMO-AAA']);
        $ba = Course::factory()->create(['code' => 'DEMO-BBB']);
        Student::factory()->create(['student_no' => 'DEMO-555001', 'last_name' => 'Aquino', 'first_name' => 'Maria', 'course_id' => $cs->id, 'year_level' => 1]);
        Student::factory()->create(['student_no' => 'DEMO-555002', 'last_name' => 'Bautista', 'first_name' => 'Pedro', 'course_id' => $ba->id, 'year_level' => 3]);
        $officer = $this->makeOfficer();

        $this->actingAs($officer)->get(route('students.index', ['q' => 'DEMO-555001']))->assertSee('Aquino')->assertDontSee('Bautista');
        $this->actingAs($officer)->get(route('students.index', ['q' => 'bautista']))->assertSee('Bautista')->assertDontSee('Aquino');
        $this->actingAs($officer)->get(route('students.index', ['q' => 'pedro bautista']))->assertSee('Bautista');
        $this->actingAs($officer)->get(route('students.index', ['course_id' => $cs->id]))->assertSee('Aquino')->assertDontSee('Bautista');
        $this->actingAs($officer)->get(route('students.index', ['year_level' => 3]))->assertSee('Bautista')->assertDontSee('Aquino');
    }

    public function test_search_treats_wildcards_literally_and_resists_injection(): void
    {
        Student::factory()->create(['last_name' => 'Safe']);

        $this->actingAs($this->makeOfficer())
            ->get(route('students.index', ['q' => "'; DROP TABLE students; --"]))
            ->assertOk();

        $this->assertSame(1, Student::count());
    }

    public function test_student_data_is_escaped_in_the_page(): void
    {
        $student = Student::factory()->create(['last_name' => '<script>alert(1)</script>']);

        $this->actingAs($this->makeAdmin())
            ->get(route('students.show', $student))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_officers_can_view_students_but_not_manage_them(): void
    {
        $officer = $this->makeOfficer();
        $student = Student::factory()->create();

        $this->actingAs($officer)->get(route('students.show', $student))->assertOk();
        $this->actingAs($officer)->get(route('students.edit', $student))->assertForbidden();
        $this->actingAs($officer)->delete(route('students.archive', $student))->assertForbidden();
        $this->assertNotSoftDeleted($student);
    }
}
