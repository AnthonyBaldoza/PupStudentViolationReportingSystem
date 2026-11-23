<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    public function test_admin_can_approve_a_pending_report(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);
        $record = $this->submitReport($officer, $student, $category);

        $this->actingAs($admin)
            ->post(route('records.approve', $record), ['remarks' => 'Verified with the guard on duty.'])
            ->assertRedirect(route('records.show', $record))
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame(ViolationRecord::APPROVED, $record->status);
        $this->assertSame(1, $record->offense_number);
        $this->assertSame("Action {$category->id}-1", $record->action_name);
        $this->assertSame($admin->id, $record->reviewed_by);
        $this->assertNotNull($record->reviewed_at);
    }

    public function test_approval_notifies_the_officer_and_writes_an_audit_entry(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $this->actingAs($admin)->post(route('records.approve', $record));

        // "data" is a text column, so filter in PHP rather than with a JSON path query.
        $approved = $officer->notifications()->get()->filter(fn ($n) => ($n->data['title'] ?? null) === 'Report approved');
        $this->assertCount(1, $approved);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'violation.approved',
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_rejecting_requires_remarks_and_does_not_count_as_an_offense(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);
        $record = $this->submitReport($officer, $student, $category);

        $this->actingAs($admin)
            ->post(route('records.reject', $record), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');
        $this->assertSame(ViolationRecord::PENDING, $record->fresh()->status);

        $this->actingAs($admin)
            ->post(route('records.reject', $record), ['remarks' => 'Wrong student.'])
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame(ViolationRecord::REJECTED, $record->status);
        $this->assertNull($record->offense_number);
        $this->assertNull($record->action_name);
    }

    public function test_returned_report_can_be_corrected_and_resubmitted_by_its_officer(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);
        $record = $this->submitReport($officer, Student::factory()->create(), $category);

        $this->actingAs($admin)
            ->post(route('records.return', $record), ['remarks' => 'Please add the exact location.'])
            ->assertSessionHas('success');
        $this->assertSame(ViolationRecord::RETURNED, $record->fresh()->status);

        $this->actingAs($officer)->get(route('records.edit', $record))->assertOk();

        $this->actingAs($officer)->put(route('records.update', $record), [
            'violation_category_id' => $category->id,
            'incident_date' => now()->subDay()->toDateString(),
            'incident_time' => '10:15',
            'location' => 'Library, second floor',
            'description' => 'Corrected description with the exact location.',
        ])->assertRedirect(route('records.show', $record));

        $record->refresh();
        $this->assertSame(ViolationRecord::PENDING, $record->status);
        $this->assertSame('Library, second floor', $record->location);
        $this->assertNull($record->review_remarks);
        $this->assertDatabaseHas('audit_logs', ['action' => 'violation.resubmitted', 'auditable_id' => $record->id]);
    }

    public function test_only_the_filing_officer_can_edit_a_returned_report(): void
    {
        $officer = $this->makeOfficer();
        $other = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));
        $this->workflow()->returnForInfo($admin, $record, 'More detail please.');

        $this->actingAs($other)->get(route('records.edit', $record))->assertForbidden();
    }

    public function test_a_pending_or_approved_report_cannot_be_edited(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(2);

        $pending = $this->submitReport($officer, Student::factory()->create(), $category);
        $approved = $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);

        $this->actingAs($officer)->get(route('records.edit', $pending))->assertForbidden();
        $this->actingAs($officer)->get(route('records.edit', $approved))->assertForbidden();
    }

    public function test_officers_cannot_approve_reject_or_return(): void
    {
        $officer = $this->makeOfficer();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $this->actingAs($officer)->post(route('records.approve', $record))->assertForbidden();
        $this->actingAs($officer)->post(route('records.reject', $record), ['remarks' => 'x'])->assertForbidden();
        $this->actingAs($officer)->post(route('records.return', $record), ['remarks' => 'x'])->assertForbidden();

        $this->assertSame(ViolationRecord::PENDING, $record->fresh()->status);
    }

    public function test_approval_without_a_configured_rule_is_refused_with_a_clear_message(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(0); // category exists, but no rules
        $record = $this->submitReport($officer, Student::factory()->create(), $category);

        $this->actingAs($admin)
            ->post(route('records.approve', $record))
            ->assertSessionHas('error');

        $this->assertSame(ViolationRecord::PENDING, $record->fresh()->status);
    }

    public function test_admin_review_page_previews_the_offense_that_approval_would_assign(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);
        $this->approvedRecord($officer, $admin, $student, $category);
        $pending = $this->submitReport($officer, $student, $category);

        $this->actingAs($admin)->get(route('records.show', $pending))
            ->assertOk()
            ->assertSee('2nd')
            ->assertSee("Action {$category->id}-2");
    }

    public function test_approved_records_appear_in_the_student_history(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);
        $record = $this->approvedRecord($officer, $admin, $student, $category);

        $this->actingAs($admin)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee($record->reference_no)
            ->assertSee($category->name);
    }

    public function test_audit_log_entries_cannot_be_modified_or_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test.entry']);

        $log->action = 'tampered';
        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());
        $this->assertSame('test.entry', $log->fresh()->action);
    }
}
