<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    /** @return array<string, array{0: string}> */
    public static function adminOnlyPages(): array
    {
        return [
            'create student' => ['students.create'],
            'categories' => ['categories.index'],
            'new category' => ['categories.create'],
            'disciplinary actions' => ['actions.index'],
            'courses' => ['courses.index'],
            'officer accounts' => ['users.index'],
            'new account' => ['users.create'],
            'reports' => ['reports.index'],
            'print report' => ['reports.print'],
            'csv export' => ['reports.csv'],
            'audit logs' => ['audit.index'],
            'settings' => ['settings.edit'],
        ];
    }

    #[DataProvider('adminOnlyPages')]
    public function test_officers_are_forbidden_from_admin_pages(string $route): void
    {
        $this->actingAs($this->makeOfficer())->get(route($route))->assertForbidden();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_admins_can_open_admin_pages(string $route): void
    {
        $this->actingAs($this->makeAdmin())->get(route($route))->assertOk();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_guests_are_sent_to_login_for_admin_pages(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    public function test_officers_cannot_perform_admin_write_actions(): void
    {
        $officer = $this->makeOfficer();
        $course = \App\Models\Course::factory()->create();

        $this->actingAs($officer)->post(route('students.store'), [])->assertForbidden();
        $this->actingAs($officer)->post(route('categories.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($officer)->post(route('actions.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($officer)->post(route('courses.store'), ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($officer)->post(route('sections.store', $course), ['year_level' => 1, 'name' => 'A'])->assertForbidden();
        $this->actingAs($officer)->post(route('users.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($officer)->put(route('settings.update'), ['school_name' => 'X'])->assertForbidden();
    }

    public function test_admins_cannot_file_violation_reports(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('records.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('records.store'), [])->assertForbidden();
    }

    public function test_an_officer_can_only_open_their_own_reports(): void
    {
        $mine = $this->makeOfficer();
        $theirs = $this->makeOfficer();
        $category = $this->makeCategory(2);

        $myReport = $this->submitReport($mine, Student::factory()->create(), $category);
        $theirReport = $this->submitReport($theirs, Student::factory()->create(), $category);

        $this->actingAs($mine)->get(route('records.show', $myReport))->assertOk();
        $this->actingAs($mine)->get(route('records.show', $theirReport))->assertForbidden();
    }

    public function test_an_officers_report_list_never_includes_other_officers_reports(): void
    {
        $mine = $this->makeOfficer();
        $theirs = $this->makeOfficer();
        $category = $this->makeCategory(2);

        $myReport = $this->submitReport($mine, Student::factory()->create(), $category);
        $theirReport = $this->submitReport($theirs, Student::factory()->create(), $category);

        $this->actingAs($mine)->get(route('records.index'))
            ->assertOk()
            ->assertSee($myReport->reference_no)
            ->assertDontSee($theirReport->reference_no);
    }

    public function test_admins_can_open_any_report(): void
    {
        $officer = $this->makeOfficer();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $this->actingAs($this->makeAdmin())->get(route('records.show', $record))->assertOk();
    }

    public function test_officers_see_verified_history_but_not_other_officers_unreviewed_reports(): void
    {
        $viewer = $this->makeOfficer();
        $other = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $approved = $this->approvedRecord($other, $admin, $student, $category);
        $othersPending = $this->submitReport($other, $student, $category);
        $myPending = $this->submitReport($viewer, $student, $category);

        $this->actingAs($viewer)->get(route('students.show', $student))
            ->assertOk()
            ->assertSee($approved->reference_no)
            ->assertSee($myPending->reference_no)
            ->assertDontSee($othersPending->reference_no);
    }

    public function test_the_student_lookup_requires_login_and_returns_only_minimal_fields(): void
    {
        Student::factory()->create(['last_name' => 'Zzyzx', 'student_no' => 'DEMO-777777']);

        $this->getJson(route('students.lookup', ['q' => 'Zzyzx']))->assertUnauthorized();

        $this->actingAs($this->makeOfficer())
            ->getJson(route('students.lookup', ['q' => 'Zzyzx']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([['id', 'student_no', 'name', 'course', 'year_level']]);
    }

    public function test_evidence_is_private_and_only_downloadable_by_authorised_users(): void
    {
        Storage::fake('local');

        $owner = $this->makeOfficer();
        $stranger = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(2);

        $record = $this->workflow()->submit($owner, [
            'student_id' => Student::factory()->create()->id,
            'violation_category_id' => $category->id,
            'incident_date' => now()->subDay()->toDateString(),
            'location' => 'Gate',
            'description' => 'Evidence upload test.',
        ], [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')]);

        $evidence = $record->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);

        $this->get(route('evidence.show', $evidence))->assertRedirect(route('login'));
        $this->actingAs($stranger)->get(route('evidence.show', $evidence))->assertForbidden();
        $this->actingAs($owner)->get(route('evidence.show', $evidence))->assertOk();
        $this->actingAs($admin)->get(route('evidence.show', $evidence))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'evidence.downloaded', 'auditable_id' => $record->id]);
    }

    public function test_users_cannot_read_other_peoples_notifications(): void
    {
        $a = $this->makeOfficer();
        $b = $this->makeOfficer();
        $a->notify(new \App\Notifications\RecordNotification('Hello', 'Private', null));
        $notification = $a->notifications()->firstOrFail();

        $this->actingAs($b)->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_roles_cannot_be_self_assigned_through_mass_assignment_on_login_or_password_forms(): void
    {
        $officer = $this->makeOfficer(['password' => 'Temp-Pass-123']);

        $this->actingAs($officer)->put(route('password.update'), [
            'current_password' => 'Temp-Pass-123',
            'password' => 'Brand-New-Pass-456',
            'password_confirmation' => 'Brand-New-Pass-456',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertSame(User::ROLE_OFFICER, $officer->fresh()->role);
    }

    public function test_violation_record_policy_is_enforced_for_inactive_users(): void
    {
        $officer = $this->makeOfficer();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));

        $officer->update(['is_active' => false]);

        $this->assertFalse($officer->can('view', $record));
        $this->assertInstanceOf(ViolationRecord::class, $record);
    }
}
