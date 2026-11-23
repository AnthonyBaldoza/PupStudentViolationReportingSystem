<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\Appeal;
use App\Models\DisciplinaryAction;
use App\Models\Student;
use App\Models\ViolationRecord;
use App\Services\OffenseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class OffenseCalculationTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    public function test_offenses_are_numbered_in_order_within_a_category(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $numbers = [];
        foreach (range(1, 3) as $ignored) {
            $numbers[] = $this->approvedRecord($officer, $admin, $student, $category)->offense_number;
        }

        $this->assertSame([1, 2, 3], $numbers);
    }

    public function test_each_category_is_counted_separately_for_the_same_student(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();

        $hair = $this->makeCategory(3, false, 'Test Hair');
        $uniform = $this->makeCategory(3, false, 'Test Uniform');
        $idCard = $this->makeCategory(3, false, 'Test ID');

        // Interleaved on purpose: hair x2, uniform x1, id x3.
        $this->approvedRecord($officer, $admin, $student, $hair);
        $this->approvedRecord($officer, $admin, $student, $idCard);
        $uniformRecord = $this->approvedRecord($officer, $admin, $student, $uniform);
        $this->approvedRecord($officer, $admin, $student, $idCard);
        $hairRecord = $this->approvedRecord($officer, $admin, $student, $hair);
        $idRecord = $this->approvedRecord($officer, $admin, $student, $idCard);

        $this->assertSame(2, $hairRecord->offense_number);
        $this->assertSame(1, $uniformRecord->offense_number);
        $this->assertSame(3, $idRecord->offense_number);
    }

    public function test_offense_counts_are_per_student(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);
        [$a, $b] = [Student::factory()->create(), Student::factory()->create()];

        $this->approvedRecord($officer, $admin, $a, $category);
        $this->approvedRecord($officer, $admin, $a, $category);

        $this->assertSame(1, $this->approvedRecord($officer, $admin, $b, $category)->offense_number);
    }

    public function test_pending_and_rejected_reports_do_not_increase_the_count(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $pending = $this->submitReport($officer, $student, $category);
        $rejected = $this->submitReport($officer, $student, $category);
        $this->workflow()->reject($admin, $rejected, 'Not enough details.');

        $this->assertSame(0, app(OffenseService::class)->approvedCount($student->id, $category->id));

        $third = $this->approve($admin, $this->submitReport($officer, $student, $category));
        $this->assertSame(1, $third->offense_number);

        // The earlier pending report now becomes the 2nd, because only approvals count.
        $this->assertSame(2, $this->approve($admin, $pending)->offense_number);
    }

    public function test_returned_reports_do_not_count_either(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $returned = $this->submitReport($officer, $student, $category);
        $this->workflow()->returnForInfo($admin, $returned, 'Add the time.');

        $this->assertSame(1, $this->approvedRecord($officer, $admin, $student, $category)->offense_number);
    }

    public function test_the_matching_disciplinary_action_is_assigned_from_the_configured_rule(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $first = $this->approvedRecord($officer, $admin, $student, $category);
        $second = $this->approvedRecord($officer, $admin, $student, $category);

        $this->assertSame("Action {$category->id}-1", $first->action_name);
        $this->assertSame("Action {$category->id}-2", $second->action_name);
    }

    public function test_succeeding_rule_applies_to_every_later_offense(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3, true); // 1st, 2nd, "3rd and succeeding"

        $records = [];
        foreach (range(1, 5) as $ignored) {
            $records[] = $this->approvedRecord($officer, $admin, $student, $category);
        }

        $this->assertSame(5, $records[4]->offense_number);
        $this->assertSame("Action {$category->id}-3", $records[3]->action_name);
        $this->assertSame("Action {$category->id}-3", $records[4]->action_name);
    }

    public function test_approval_is_blocked_when_no_rule_exists_for_the_offense_number(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(1); // only a 1st-offense rule

        $this->approvedRecord($officer, $admin, $student, $category);
        $second = $this->submitReport($officer, $student, $category);

        try {
            $this->approve($admin, $second);
            $this->fail('Approval should have been blocked.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('No active offense rule', $e->getMessage());
        }

        $second->refresh();
        $this->assertSame(ViolationRecord::PENDING, $second->status);
        $this->assertNull($second->offense_number);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(1);
        \App\Models\OffenseRule::where('violation_category_id', $category->id)->update(['is_active' => false]);

        $this->expectException(WorkflowException::class);
        $this->approve($admin, $this->submitReport($officer, $student, $category));
    }

    public function test_changing_rules_later_does_not_alter_approved_records(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2, false, 'Original Name');

        $record = $this->approvedRecord($officer, $admin, $student, $category);
        $originalAction = $record->action_name;

        // The school later changes the penalty, its wording, and renames the category.
        $newAction = DisciplinaryAction::factory()->create(['name' => 'Completely different penalty']);
        \App\Models\OffenseRule::where('violation_category_id', $category->id)->where('offense_number', 1)
            ->update(['disciplinary_action_id' => $newAction->id, 'description' => 'New wording']);
        $category->update(['name' => 'Renamed Category']);

        $record->refresh();
        $this->assertSame($originalAction, $record->action_name);
        $this->assertSame('Original Name', $record->category_name);
        $this->assertSame(1, $record->offense_number);
    }

    public function test_deactivated_categories_keep_their_history(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);

        $record = $this->approvedRecord($officer, $admin, $student, $category);
        $category->update(['is_active' => false]);

        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);
        $this->assertSame(1, app(OffenseService::class)->approvedCount($student->id, $category->id));

        $this->expectException(WorkflowException::class); // but no NEW reports
        $this->submitReport($officer, $student, $category);
    }

    public function test_an_approved_or_rejected_report_cannot_be_reviewed_again(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $record = $this->approvedRecord($officer, $admin, $student, $category);

        $this->expectException(WorkflowException::class);
        $this->approve($admin, $record);
    }

    public function test_a_user_cannot_review_their_own_report(): void
    {
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $own = $this->submitReport($admin, $student, $category);

        $this->expectException(WorkflowException::class);
        $this->approve($admin, $own);
    }

    public function test_voided_records_stop_counting_but_their_number_is_never_reused(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(4);

        $first = $this->approvedRecord($officer, $admin, $student, $category);   // 1st
        $this->approvedRecord($officer, $admin, $student, $category);            // 2nd

        $appeal = $this->workflow()->fileAppeal($admin, $first, 'The student was not present that day.');
        $this->workflow()->decideAppeal($admin, $appeal, Appeal::OVERTURNED, 'Attendance proves the student was absent.');

        $this->assertSame(ViolationRecord::VOIDED, $first->fresh()->status);
        $this->assertSame(1, app(OffenseService::class)->approvedCount($student->id, $category->id));

        // Highest approved number is 2, so the next is 3 (no duplicate "2nd", no renumbering of verified records).
        $this->assertSame(3, $this->approvedRecord($officer, $admin, $student, $category)->offense_number);
    }

    public function test_the_database_refuses_two_approved_records_with_the_same_offense_number(): void
    {
        $officer = $this->makeOfficer();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $attributes = [
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'reported_by' => $officer->id,
            'status' => ViolationRecord::APPROVED,
            'offense_number' => 1,
        ];

        ViolationRecord::factory()->create($attributes);

        $this->expectException(QueryException::class);
        ViolationRecord::factory()->create($attributes);
    }

    public function test_offense_summary_for_a_student_lists_approved_records_only(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $a = $this->makeCategory(3, false, 'Cat A');
        $b = $this->makeCategory(3, false, 'Cat B');

        $this->approvedRecord($officer, $admin, $student, $a);
        $this->approvedRecord($officer, $admin, $student, $a);
        $this->submitReport($officer, $student, $b); // pending: must not appear

        $summary = app(OffenseService::class)->summaryForStudent($student);

        $this->assertCount(1, $summary);
        $this->assertSame('Cat A', $summary->first()['category']->name);
        $this->assertSame(2, $summary->first()['count']);
        $this->assertSame(3, $summary->first()['next']);
    }
}
