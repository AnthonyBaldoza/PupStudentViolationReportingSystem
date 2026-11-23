<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DisciplinaryAction;
use App\Models\OffenseRule;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    // ------------------------------------------------------------ categories & rules

    public function test_admin_creates_a_category_and_configures_its_offense_progression(): void
    {
        $admin = $this->makeAdmin();
        $actions = collect(['One', 'Two', 'Three'])->map(fn ($n) => DisciplinaryAction::factory()->create(['name' => "Penalty {$n}"]));

        $this->actingAs($admin)->post(route('categories.store'), ['name' => 'Configurable Category', 'description' => 'Set by the school.'])
            ->assertRedirect();
        $category = ViolationCategory::where('name', 'Configurable Category')->firstOrFail();

        foreach ([1, 2] as $i => $n) {
            $this->actingAs($admin)->post(route('rules.store', $category), [
                'offense_number' => $n,
                'disciplinary_action_id' => $actions[$i]->id,
                'description' => "Rule {$n}",
            ])->assertSessionHasNoErrors();
        }
        $this->actingAs($admin)->post(route('rules.store', $category), [
            'offense_number' => 3,
            'is_succeeding' => '1',
            'disciplinary_action_id' => $actions[2]->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $category->rules()->count());
        $this->assertTrue($category->rules()->where('offense_number', 3)->first()->is_succeeding);
        $this->actingAs($admin)->get(route('categories.edit', $category))->assertOk()
            ->assertSee('3rd and succeeding offenses');
    }

    public function test_each_category_can_only_have_one_rule_per_offense_number(): void
    {
        $category = $this->makeCategory(2);
        $action = DisciplinaryAction::factory()->create();

        $this->actingAs($this->makeAdmin())->post(route('rules.store', $category), [
            'offense_number' => 2,
            'disciplinary_action_id' => $action->id,
        ])->assertSessionHasErrors('offense_number');

        $this->assertSame(2, $category->rules()->count());
    }

    public function test_the_same_offense_number_is_allowed_in_different_categories(): void
    {
        $a = $this->makeCategory(1);
        $b = ViolationCategory::factory()->create();
        $action = DisciplinaryAction::factory()->create();

        $this->actingAs($this->makeAdmin())->post(route('rules.store', $b), [
            'offense_number' => 1,
            'disciplinary_action_id' => $action->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $b->rules()->count());
        $this->assertSame(1, $a->rules()->count());
    }

    public function test_inactive_actions_cannot_be_attached_to_new_rules(): void
    {
        $category = ViolationCategory::factory()->create();
        $inactive = DisciplinaryAction::factory()->create(['is_active' => false]);

        $this->actingAs($this->makeAdmin())->post(route('rules.store', $category), [
            'offense_number' => 1,
            'disciplinary_action_id' => $inactive->id,
        ])->assertSessionHasErrors('disciplinary_action_id');
    }

    public function test_editing_a_rule_is_audited_and_leaves_existing_records_alone(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(2);
        $record = $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);
        $rule = $category->rules()->where('offense_number', 1)->first();
        $newAction = DisciplinaryAction::factory()->create(['name' => 'Updated penalty']);

        $this->actingAs($admin)->put(route('rules.update', $rule), [
            'offense_number' => 1,
            'disciplinary_action_id' => $newAction->id,
            'description' => 'Changed wording',
        ])->assertSessionHasNoErrors();

        $this->assertSame($newAction->id, $rule->fresh()->disciplinary_action_id);
        $this->assertSame("Action {$category->id}-1", $record->fresh()->action_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rule.updated', 'auditable_id' => $rule->id]);
    }

    public function test_category_names_must_be_unique(): void
    {
        ViolationCategory::factory()->create(['name' => 'Taken Name']);

        $this->actingAs($this->makeAdmin())->post(route('categories.store'), ['name' => 'Taken Name'])
            ->assertSessionHasErrors('name');
    }

    public function test_deactivating_and_reactivating_a_category_is_audited_and_keeps_history(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(2);
        $record = $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);

        $this->actingAs($admin)->post(route('categories.toggle', $category))->assertSessionHas('success');
        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deactivated', 'auditable_id' => $category->id]);
        $this->assertSame(ViolationRecord::APPROVED, $record->fresh()->status);

        $this->actingAs($admin)->post(route('categories.toggle', $category));
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_deactivating_a_rule_stops_it_being_used_but_keeps_past_records(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);
        $first = $this->approvedRecord($officer, $admin, $student, $category);

        $this->actingAs($admin)->post(route('rules.toggle', $category->rules()->where('offense_number', 2)->first()));
        $second = $this->submitReport($officer, $student, $category);

        $this->actingAs($admin)->post(route('records.approve', $second))->assertSessionHas('error');
        $this->assertSame(ViolationRecord::PENDING, $second->fresh()->status);
        $this->assertSame(1, $first->fresh()->offense_number);
    }

    public function test_disciplinary_actions_can_be_created_edited_and_deactivated(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('actions.store'), ['name' => 'Written warning', 'description' => 'Placeholder'])->assertSessionHasNoErrors();
        $action = DisciplinaryAction::where('name', 'Written warning')->firstOrFail();

        $this->actingAs($admin)->put(route('actions.update', $action), ['name' => 'Written warning (updated)'])->assertSessionHasNoErrors();
        $this->assertSame('Written warning (updated)', $action->fresh()->name);

        $this->actingAs($admin)->post(route('actions.toggle', $action));
        $this->assertFalse($action->fresh()->is_active);
    }

    public function test_nothing_is_preloaded_by_the_production_seeder(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(0, ViolationCategory::count());
        $this->assertSame(0, DisciplinaryAction::count());
        $this->assertSame(0, OffenseRule::count());
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertTrue(User::where('role', 'admin')->first()->must_change_password);
    }

    // ------------------------------------------------------------ accounts

    public function test_admin_creates_an_officer_account_with_a_one_time_temporary_password(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Demo Officer',
            'email' => 'Demo.Officer@Example.test',
            'position' => 'Secretary',
            'role' => 'officer',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'demo.officer@example.test')->firstOrFail();
        $temporary = session('temp_password');

        $this->assertNotEmpty($temporary);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->isOfficer());
        $this->assertTrue(Hash::check($temporary, $user->password));
        $this->assertNotSame($temporary, $user->getRawOriginal('password'));

        // The temporary password must never be written to the audit trail.
        $this->assertStringNotContainsString($temporary, json_encode(\App\Models\AuditLog::all()->toArray()));
        $this->assertNotNull($response);

        // And the new officer is forced to choose their own password.
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'demo.officer@example.test', 'password' => $temporary]);
        $this->get(route('dashboard'))->assertRedirect(route('password.edit'));
    }

    public function test_account_emails_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($this->makeAdmin())->post(route('users.store'), [
            'name' => 'Another', 'email' => 'TAKEN@example.test', 'role' => 'officer',
        ])->assertSessionHasErrors('email');
    }

    public function test_invalid_roles_are_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'role' => 'superuser',
        ])->assertSessionHasErrors('role');
    }

    public function test_admin_can_deactivate_an_officer_who_is_then_locked_out(): void
    {
        $admin = $this->makeAdmin();
        $officer = $this->makeOfficer(['password' => 'Correct-Horse-9']);

        $this->actingAs($admin)->post(route('users.toggle', $officer))->assertSessionHas('success');
        $this->assertFalse($officer->fresh()->is_active);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $officer->email, 'password' => 'Correct-Horse-9'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admins_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('users.toggle', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'officer',
        ]);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_can_reset_an_officers_password(): void
    {
        $admin = $this->makeAdmin();
        $officer = $this->makeOfficer(['password' => 'Old-Password-123']);

        $this->actingAs($admin)->post(route('users.resetPassword', $officer))->assertSessionHas('temp_password');

        $officer->refresh();
        $this->assertFalse(Hash::check('Old-Password-123', $officer->password));
        $this->assertTrue($officer->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'auditable_id' => $officer->id]);
    }

    // ------------------------------------------------------------ courses & sections

    public function test_admin_manages_courses_and_sections(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('courses.store'), ['code' => 'demo-xyz', 'name' => 'Demo Program'])->assertSessionHasNoErrors();
        $course = Course::where('code', 'DEMO-XYZ')->firstOrFail();

        $this->actingAs($admin)->post(route('courses.store'), ['code' => 'DEMO-XYZ', 'name' => 'Duplicate'])->assertSessionHasErrors('code');

        $this->actingAs($admin)->post(route('sections.store', $course), ['year_level' => 2, 'name' => 'A'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('sections.store', $course), ['year_level' => 2, 'name' => 'A'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('sections.store', $course), ['year_level' => 3, 'name' => 'A'])->assertSessionHasNoErrors();

        $this->assertSame(2, $course->sections()->count());
        $this->actingAs($admin)->get(route('courses.index'))->assertOk()->assertSee('DEMO-XYZ');
    }

    public function test_a_section_with_students_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $course = Course::factory()->create();
        $used = Section::factory()->create(['course_id' => $course->id]);
        $empty = Section::factory()->create(['course_id' => $course->id]);
        Student::factory()->create(['course_id' => $course->id, 'section_id' => $used->id]);

        $this->actingAs($admin)->delete(route('sections.destroy', $used))->assertSessionHas('error');
        $this->assertDatabaseHas('sections', ['id' => $used->id]);

        $this->actingAs($admin)->delete(route('sections.destroy', $empty))->assertSessionHas('success');
        $this->assertDatabaseMissing('sections', ['id' => $empty->id]);
    }

    // ------------------------------------------------------------ reports

    public function test_reports_can_be_filtered_by_status_category_course_year_and_offense_number(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $catA = $this->makeCategory(3, false, 'Report Cat A');
        $catB = $this->makeCategory(3, false, 'Report Cat B');
        $cs = Course::factory()->create();
        $ba = Course::factory()->create();
        $s1 = Student::factory()->create(['course_id' => $cs->id, 'year_level' => 1]);
        $s2 = Student::factory()->create(['course_id' => $ba->id, 'year_level' => 4]);

        $a1 = $this->approvedRecord($officer, $admin, $s1, $catA);          // A, 1st, cs, Y1
        $a2 = $this->approvedRecord($officer, $admin, $s1, $catA);          // A, 2nd, cs, Y1
        $b1 = $this->approvedRecord($officer, $admin, $s2, $catB);          // B, 1st, ba, Y4
        $pending = $this->submitReport($officer, $s2, $catA);               // A, pending, ba, Y4

        $see = fn (array $f, array $yes, array $no) => tap(
            $this->actingAs($admin)->get(route('reports.index', $f))->assertOk(),
            function ($r) use ($yes, $no) {
                foreach ($yes as $rec) { $r->assertSee($rec->reference_no); }
                foreach ($no as $rec) { $r->assertDontSee($rec->reference_no); }
            }
        );

        $see(['category_id' => $catB->id], [$b1], [$a1, $a2, $pending]);
        $see(['status' => 'pending'], [$pending], [$a1, $a2, $b1]);
        $see(['course_id' => $cs->id], [$a1, $a2], [$b1, $pending]);
        $see(['year_level' => 4], [$b1, $pending], [$a1, $a2]);
        $see(['offense_number' => 2], [$a2], [$a1, $b1, $pending]);
        $see(['q' => $s1->student_no], [$a1, $a2], [$b1, $pending]);
    }

    public function test_report_date_range_filter_and_validation(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);
        $student = Student::factory()->create();

        $old = $this->submitReport($officer, $student, $category, ['incident_date' => '2026-01-10']);
        $recent = $this->submitReport($officer, $student, $category, ['incident_date' => '2026-03-10']);

        $this->actingAs($admin)->get(route('reports.index', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
            ->assertSee($recent->reference_no)
            ->assertDontSee($old->reference_no);

        $this->actingAs($admin)->get(route('reports.index', ['date_from' => '2026-03-31', 'date_to' => '2026-03-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_report_summary_statistics_are_computed_from_real_data(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);

        $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);
        $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);
        $this->submitReport($officer, Student::factory()->create(), $category);

        $this->actingAs($admin)->get(route('reports.index'))
            ->assertViewHas('summary', fn ($s) => $s['total'] === 3
                && (int) $s['by_status']['approved'] === 2
                && (int) $s['by_status']['pending'] === 1);
    }

    public function test_csv_export_respects_filters_and_neutralises_spreadsheet_formulas(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);
        $evil = Student::factory()->create(['last_name' => '=cmd|calc', 'first_name' => 'Evil']);
        $normal = Student::factory()->create(['last_name' => 'Normal']);

        $evilRecord = $this->approvedRecord($officer, $admin, $evil, $category);
        $normalRecord = $this->submitReport($officer, $normal, $category);

        $response = $this->actingAs($admin)->get(route('reports.csv', ['status' => 'approved']))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Reference,"Student ID"', $csv);
        $this->assertStringContainsString($evilRecord->reference_no, $csv);
        $this->assertStringNotContainsString($normalRecord->reference_no, $csv); // pending, filtered out
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringNotContainsString('"=cmd|calc', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_the_printable_report_renders_with_summary_and_filters(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);
        $record = $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);

        $this->actingAs($admin)->get(route('reports.print', ['category_id' => $category->id]))
            ->assertOk()
            ->assertSee($record->reference_no)
            ->assertSee($category->name)
            ->assertSee('Total reports');
    }

    // ------------------------------------------------------------ settings & audit

    public function test_admin_can_update_settings_and_the_change_is_audited(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('settings.update'), [
            'school_name' => 'Sample Demo School',
            'council_name' => 'Demo Student Council',
            'report_footer' => 'Confidential.',
            'dpo_contact' => 'dpo@example.test',
            'retention_policy' => 'Kept per school policy.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Sample Demo School', Setting::get('school_name'));
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Demo Student Council');
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_the_audit_log_page_lists_and_filters_entries(): void
    {
        $admin = $this->makeAdmin();
        $officer = $this->makeOfficer();
        $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('violation.submitted');
        $this->actingAs($admin)->get(route('audit.index', ['action' => 'auth.']))->assertOk()->assertDontSee('violation.submitted');
    }
}
