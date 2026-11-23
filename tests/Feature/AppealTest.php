<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\Student;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class AppealTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    private function approved(): array
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        return [$officer, $admin, $student, $category, $this->approvedRecord($officer, $admin, $student, $category)];
    }

    public function test_admin_can_log_an_appeal_for_an_approved_record(): void
    {
        [, $admin, , , $record] = $this->approved();

        $this->actingAs($admin)
            ->post(route('appeals.store', $record), ['statement' => 'The student says they were excused by their adviser.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('appeals', ['violation_record_id' => $record->id, 'status' => Appeal::PENDING, 'filed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appeal.filed', 'auditable_id' => $record->id]);

        // Filing an appeal changes nothing about the record until it is decided.
        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->offense_number);
    }

    public function test_only_approved_records_can_be_appealed(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $pending = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $this->actingAs($admin)
            ->post(route('appeals.store', $pending), ['statement' => 'This should not be accepted.'])
            ->assertSessionHas('error');

        $this->assertSame(0, Appeal::count());
    }

    public function test_a_record_cannot_have_two_pending_appeals(): void
    {
        [, $admin, , , $record] = $this->approved();
        $this->workflow()->fileAppeal($admin, $record, 'First appeal statement.');

        $this->actingAs($admin)
            ->post(route('appeals.store', $record), ['statement' => 'Second appeal statement.'])
            ->assertSessionHas('error');

        $this->assertSame(1, Appeal::count());
    }

    public function test_an_upheld_appeal_leaves_the_record_and_its_offense_number_intact(): void
    {
        [, $admin, $student, $category, $record] = $this->approved();
        $appeal = $this->workflow()->fileAppeal($admin, $record, 'Student disputes the incident.');

        $this->actingAs($admin)
            ->post(route('appeals.decide', $appeal), ['decision' => 'upheld', 'remarks' => 'CCTV confirms the incident.'])
            ->assertSessionHas('success');

        $this->assertSame(Appeal::UPHELD, $appeal->fresh()->status);
        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->offense_number);
        $this->assertSame(1, app(\App\Services\OffenseService::class)->approvedCount($student->id, $category->id));
    }

    public function test_an_overturned_appeal_voids_but_never_deletes_the_record(): void
    {
        [, $admin, $student, $category, $record] = $this->approved();
        $appeal = $this->workflow()->fileAppeal($admin, $record, 'Mistaken identity.');

        $this->actingAs($admin)
            ->post(route('appeals.decide', $appeal), ['decision' => 'overturned', 'remarks' => 'Wrong student was identified.'])
            ->assertSessionHas('success');

        $record->refresh();
        $this->assertSame(ViolationRecord::VOIDED, $record->status);
        $this->assertSame('Wrong student was identified.', $record->void_reason);
        $this->assertNotNull($record->voided_at);
        $this->assertSame(1, $record->offense_number, 'the original number stays on the record for history');
        $this->assertDatabaseHas('violation_records', ['id' => $record->id]);
        $this->assertSame(0, app(\App\Services\OffenseService::class)->approvedCount($student->id, $category->id));

        $this->assertDatabaseHas('audit_logs', ['action' => 'violation.voided_on_appeal', 'auditable_id' => $record->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appeal.decided', 'auditable_id' => $record->id]);
    }

    public function test_an_appeal_can_only_be_decided_once(): void
    {
        [, $admin, , , $record] = $this->approved();
        $appeal = $this->workflow()->fileAppeal($admin, $record, 'Dispute statement text.');
        $this->workflow()->decideAppeal($admin, $appeal, Appeal::UPHELD, 'Stands.');

        $this->actingAs($admin)
            ->post(route('appeals.decide', $appeal), ['decision' => 'overturned', 'remarks' => 'Changing my mind.'])
            ->assertSessionHas('error');

        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);
    }

    public function test_decision_must_be_valid_and_explained(): void
    {
        [, $admin, , , $record] = $this->approved();
        $appeal = $this->workflow()->fileAppeal($admin, $record, 'Dispute statement text.');

        $this->actingAs($admin)
            ->post(route('appeals.decide', $appeal), ['decision' => 'delete-it', 'remarks' => ''])
            ->assertSessionHasErrors(['decision', 'remarks']);

        $this->assertSame(Appeal::PENDING, $appeal->fresh()->status);
    }

    public function test_officers_cannot_file_or_decide_appeals(): void
    {
        [$officer, $admin, , , $record] = $this->approved();
        $appeal = $this->workflow()->fileAppeal($admin, $record, 'Dispute statement text.');

        $this->actingAs($officer)->post(route('appeals.store', $record), ['statement' => 'Officer tries to appeal.'])->assertForbidden();
        $this->actingAs($officer)->post(route('appeals.decide', $appeal), ['decision' => 'overturned', 'remarks' => 'x'])->assertForbidden();

        $this->assertSame(Appeal::PENDING, $appeal->fresh()->status);
    }

    public function test_the_record_page_shows_appeal_history_to_admins(): void
    {
        [, $admin, , , $record] = $this->approved();
        $this->workflow()->fileAppeal($admin, $record, 'Visible appeal statement.');

        $this->actingAs($admin)->get(route('records.show', $record))
            ->assertOk()
            ->assertSee('Visible appeal statement.')
            ->assertSee('Appeals / disputes');
    }
}
