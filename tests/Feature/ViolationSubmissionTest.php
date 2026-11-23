<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Models\ViolationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesViolationFixtures;
use Tests\TestCase;

class ViolationSubmissionTest extends TestCase
{
    use RefreshDatabase, CreatesViolationFixtures;

    private function payload(Student $student, $category, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'incident_date' => now()->subDay()->toDateString(),
            'incident_time' => '08:45',
            'location' => 'Main Gate',
            'description' => 'Student was observed at the gate during class hours.',
        ], $overrides);
    }

    public function test_officer_can_open_the_new_report_form(): void
    {
        $category = $this->makeCategory(2);

        $this->actingAs($this->makeOfficer())->get(route('records.create'))
            ->assertOk()
            ->assertSee($category->name);
    }

    public function test_officer_submits_a_report_which_starts_as_pending_without_an_offense_number(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $student = Student::factory()->create();
        $category = $this->makeCategory(3);

        $response = $this->actingAs($officer)->post(route('records.store'), $this->payload($student, $category));

        $record = ViolationRecord::firstOrFail();
        $response->assertRedirect(route('records.show', $record));

        $this->assertSame(ViolationRecord::PENDING, $record->status);
        $this->assertNull($record->offense_number);
        $this->assertNull($record->action_name);
        $this->assertSame($officer->id, $record->reported_by);
        $this->assertSame($category->name, $record->category_name);
        $this->assertMatchesRegularExpression('/^VR-\d{4}-\d{6}$/', $record->reference_no);

        // The admin is told there is something to review, and the submission is audited.
        $this->assertCount(1, $admin->notifications);
        $this->assertDatabaseHas('audit_logs', ['action' => 'violation.submitted', 'auditable_id' => $record->id]);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->makeOfficer())
            ->post(route('records.store'), [])
            ->assertSessionHasErrors(['student_id', 'violation_category_id', 'incident_date', 'location', 'description']);

        $this->assertSame(0, ViolationRecord::count());
    }

    public function test_future_dates_and_too_short_descriptions_are_rejected(): void
    {
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);

        $this->actingAs($this->makeOfficer())
            ->post(route('records.store'), $this->payload($student, $category, [
                'incident_date' => now()->addDays(3)->toDateString(),
                'description' => 'short',
            ]))
            ->assertSessionHasErrors(['incident_date', 'description']);
    }

    public function test_inactive_categories_cannot_be_used_for_new_reports(): void
    {
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);
        $category->update(['is_active' => false]);

        $this->actingAs($this->makeOfficer())
            ->post(route('records.store'), $this->payload($student, $category))
            ->assertSessionHasErrors('violation_category_id');

        $this->assertSame(0, ViolationRecord::count());
    }

    public function test_the_form_only_offers_active_categories(): void
    {
        $active = $this->makeCategory(1, false, 'Visible Category');
        $inactive = $this->makeCategory(1, false, 'Hidden Category');
        $inactive->update(['is_active' => false]);

        $this->actingAs($this->makeOfficer())->get(route('records.create'))
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);
    }

    public function test_unknown_students_are_rejected(): void
    {
        $category = $this->makeCategory(2);

        $this->actingAs($this->makeOfficer())
            ->post(route('records.store'), [
                'student_id' => 987654,
                'violation_category_id' => $category->id,
                'incident_date' => now()->subDay()->toDateString(),
                'location' => 'Gate',
                'description' => 'A long enough description.',
            ])
            ->assertSessionHasErrors('student_id');
    }

    public function test_evidence_files_are_stored_privately_and_recorded(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $officer = $this->makeOfficer();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);

        $this->actingAs($officer)->post(route('records.store'), $this->payload($student, $category) + [
            'evidence' => [
                UploadedFile::fake()->image('photo.jpg', 600, 400),
                UploadedFile::fake()->create('statement.pdf', 200, 'application/pdf'),
            ],
        ])->assertSessionHasNoErrors();

        $record = ViolationRecord::with('evidence')->firstOrFail();
        $this->assertCount(2, $record->evidence);

        foreach ($record->evidence as $file) {
            Storage::disk('local')->assertExists($file->path);
            Storage::disk('public')->assertMissing($file->path); // never in the public web root
            $this->assertSame(64, strlen($file->sha256));
            $this->assertStringNotContainsString('photo', $file->path); // random server-side name
            $this->assertStringNotContainsString('statement', $file->path);
        }
    }

    public function test_dangerous_or_oversized_evidence_is_rejected(): void
    {
        Storage::fake('local');

        $officer = $this->makeOfficer();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);

        $bad = [
            'script' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'exe' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
            'html' => UploadedFile::fake()->create('page.html', 10, 'text/html'),
            'huge' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'), // > 5 MB
        ];

        foreach ($bad as $label => $file) {
            $this->actingAs($officer)
                ->post(route('records.store'), $this->payload($student, $category) + ['evidence' => [$file]])
                ->assertSessionHasErrors('evidence.0', "{$label} should be refused");
        }

        $this->assertSame(0, ViolationRecord::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_no_more_than_five_evidence_files_are_accepted(): void
    {
        Storage::fake('local');

        $files = array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 6));

        $this->actingAs($this->makeOfficer())
            ->post(route('records.store'), $this->payload(Student::factory()->create(), $this->makeCategory(2)) + ['evidence' => $files])
            ->assertSessionHasErrors('evidence');
    }

    public function test_description_html_is_escaped_when_displayed(): void
    {
        $officer = $this->makeOfficer();
        $student = Student::factory()->create();
        $category = $this->makeCategory(2);

        $record = $this->submitReport($officer, $student, $category, ['description' => '<img src=x onerror=alert(1)> plain text here']);

        $this->actingAs($officer)->get(route('records.show', $record))
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_an_officer_sees_the_status_of_their_submitted_reports(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);

        $pending = $this->submitReport($officer, Student::factory()->create(), $category);
        $rejected = $this->submitReport($officer, Student::factory()->create(), $category);
        $this->workflow()->reject($admin, $rejected, 'Duplicate report.');

        $this->actingAs($officer)->get(route('records.index'))
            ->assertOk()
            ->assertSee($pending->reference_no)
            ->assertSee('Pending')
            ->assertSee('Rejected');

        $this->actingAs($officer)->get(route('records.index', ['status' => 'rejected']))
            ->assertSee($rejected->reference_no)
            ->assertDontSee($pending->reference_no);
    }

    public function test_the_dashboard_reflects_real_counts_per_role(): void
    {
        $officer = $this->makeOfficer();
        $other = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(3);

        $this->submitReport($officer, Student::factory()->create(), $category);
        $this->approvedRecord($officer, $admin, Student::factory()->create(), $category);
        $this->submitReport($other, Student::factory()->create(), $category);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['reports'] === 3 && $s['pending'] === 2 && $s['approved'] === 1 && $s['students'] === 3);

        // Officers only see counts for their own reports.
        $this->actingAs($officer)->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['reports'] === 2 && $s['pending'] === 1 && $s['approved'] === 1);
    }

    public function test_the_dashboard_with_no_data_renders_without_errors(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['reports'] === 0 && $s['students'] === 0);
    }

    public function test_notification_bell_lists_status_updates_and_marks_them_read(): void
    {
        $officer = $this->makeOfficer();
        $admin = $this->makeAdmin();
        $record = $this->submitReport($officer, Student::factory()->create(), $this->makeCategory(2));
        $this->workflow()->reject($admin, $record, 'Not enough detail.');

        $this->actingAs($officer)->get(route('notifications.index'))->assertOk()->assertSee('Report rejected');
        $this->assertSame(1, $officer->unreadNotifications()->count());

        $notification = $officer->unreadNotifications()->first();
        $this->actingAs($officer)->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('records.show', $record));

        $this->assertSame(0, $officer->fresh()->unreadNotifications()->count());
        $this->assertInstanceOf(User::class, $officer);
    }
}
