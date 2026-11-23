<?php

namespace Tests\Concerns;

use App\Models\DisciplinaryAction;
use App\Models\OffenseRule;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use App\Services\ViolationWorkflowService;

/**
 * Helpers that build data through the REAL workflow service, so tests never fake offense numbers.
 */
trait CreatesViolationFixtures
{
    protected function makeAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes);
    }

    protected function makeOfficer(array $attributes = []): User
    {
        return User::factory()->officer()->create($attributes);
    }

    /**
     * A category with rules for offenses 1..$levels. When $lastSucceeding is true the final
     * rule means "Nth and succeeding offenses".
     */
    protected function makeCategory(int $levels = 3, bool $lastSucceeding = false, ?string $name = null): ViolationCategory
    {
        $category = ViolationCategory::factory()->create($name ? ['name' => $name] : []);

        for ($i = 1; $i <= $levels; $i++) {
            $action = DisciplinaryAction::factory()->create(['name' => "Action {$category->id}-{$i}"]);

            OffenseRule::factory()->create([
                'violation_category_id' => $category->id,
                'offense_number' => $i,
                'is_succeeding' => $lastSucceeding && $i === $levels,
                'disciplinary_action_id' => $action->id,
            ]);
        }

        return $category;
    }

    protected function workflow(): ViolationWorkflowService
    {
        return app(ViolationWorkflowService::class);
    }

    protected function submitReport(User $officer, Student $student, ViolationCategory $category, array $overrides = []): ViolationRecord
    {
        return $this->workflow()->submit($officer, array_merge([
            'student_id' => $student->id,
            'violation_category_id' => $category->id,
            'incident_date' => now()->subDay()->toDateString(),
            'incident_time' => '09:30',
            'location' => 'Main Gate',
            'description' => 'Test incident description.',
        ], $overrides));
    }

    protected function approve(User $admin, ViolationRecord $record, ?string $remarks = null): ViolationRecord
    {
        return $this->workflow()->approve($admin, $record, $remarks);
    }

    /** Submit and approve in one go. */
    protected function approvedRecord(User $officer, User $admin, Student $student, ViolationCategory $category): ViolationRecord
    {
        return $this->approve($admin, $this->submitReport($officer, $student, $category));
    }
}
