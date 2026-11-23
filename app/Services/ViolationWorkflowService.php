<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Appeal;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use App\Notifications\RecordNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The whole report lifecycle. Every state change runs in a DB transaction, takes row
 * locks, writes an audit entry and notifies the people involved.
 *
 *   pending --approve--> approved --appeal overturned--> voided
 *   pending --reject---> rejected
 *   pending --return---> returned --officer resubmits--> pending
 */
class ViolationWorkflowService
{
    public function __construct(
        private readonly OffenseService $offenses,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function submit(User $officer, array $data, array $files = []): ViolationRecord
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($officer, $data, $files, &$stored) {
                $category = ViolationCategory::query()->where('is_active', true)->find($data['violation_category_id']);
                if (! $category) {
                    throw new WorkflowException('The selected violation category is not active.');
                }

                $student = Student::query()->find($data['student_id']); // excludes archived students
                if (! $student) {
                    throw new WorkflowException('The selected student does not exist or has been archived.');
                }

                $record = ViolationRecord::create([
                    'student_id' => $student->id,
                    'violation_category_id' => $category->id,
                    'category_name' => $category->name,
                    'reported_by' => $officer->id,
                    'incident_date' => $data['incident_date'],
                    'incident_time' => $data['incident_time'] ?? null,
                    'location' => $data['location'],
                    'description' => $data['description'],
                    'status' => ViolationRecord::PENDING,
                ]);

                $record->update(['reference_no' => sprintf('VR-%s-%06d', now()->format('Y'), $record->id)]);

                $this->storeEvidence($record, $files, $officer, $stored);

                $this->audit->log('violation.submitted', $record, null, [
                    'student_id' => $student->id,
                    'category' => $category->name,
                    'incident_date' => (string) $data['incident_date'],
                ]);

                $this->notifyAdmins($record, 'New violation report', "{$record->reference_no} was submitted and is waiting for review.");

                return $record;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * An officer corrects a report that was returned for more information.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function resubmit(User $officer, ViolationRecord $record, array $data, array $files = []): ViolationRecord
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($officer, $record, $data, $files, &$stored) {
                $record = ViolationRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

                if ($record->reported_by !== $officer->id || $record->status !== ViolationRecord::RETURNED) {
                    throw new WorkflowException('This report can no longer be edited.');
                }

                $category = ViolationCategory::query()->where('is_active', true)->find($data['violation_category_id']);
                if (! $category) {
                    throw new WorkflowException('The selected violation category is not active.');
                }

                $old = $record->only(['violation_category_id', 'incident_date', 'incident_time', 'location', 'description', 'review_remarks']);

                $record->update([
                    'violation_category_id' => $category->id,
                    'category_name' => $category->name,
                    'incident_date' => $data['incident_date'],
                    'incident_time' => $data['incident_time'] ?? null,
                    'location' => $data['location'],
                    'description' => $data['description'],
                    'status' => ViolationRecord::PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_remarks' => null,
                ]);

                $this->storeEvidence($record, $files, $officer, $stored);

                $this->audit->log('violation.resubmitted', $record, $old, [
                    'violation_category_id' => $category->id,
                    'incident_date' => (string) $data['incident_date'],
                    'location' => $data['location'],
                ]);

                $this->notifyAdmins($record, 'Report resubmitted', "{$record->reference_no} was updated and is waiting for review again.");

                return $record;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Approve a pending report: compute the offense number for THIS student and category,
     * resolve the configured rule, and snapshot everything onto the record.
     */
    public function approve(User $admin, ViolationRecord $record, ?string $remarks = null): ViolationRecord
    {
        return DB::transaction(function () use ($admin, $record, $remarks) {
            // Serialise all approvals for the same student so two admins can never
            // be handed the same offense number (the partial unique index is the backstop).
            Student::withTrashed()->whereKey($record->student_id)->lockForUpdate()->firstOrFail();
            $record = $this->lockPending($record, $admin);

            $number = $this->offenses->nextOffenseNumber($record->student_id, $record->violation_category_id);
            $rule = $this->offenses->resolveRule($record->violation_category_id, $number);

            if (! $rule || ! $rule->action) {
                throw new WorkflowException(
                    'No active offense rule is configured for the '.\App\Support\Ordinal::format($number)
                    ." offense of \"{$record->category_name}\". Configure it under Violation Categories, then approve again."
                );
            }

            $category = ViolationCategory::find($record->violation_category_id);

            $record->update([
                'status' => ViolationRecord::APPROVED,
                'category_name' => $category?->name ?? $record->category_name,
                'offense_number' => $number,
                'offense_rule_id' => $rule->id,
                'action_name' => $rule->action->name,
                'action_description' => $rule->action->description,
                'rule_description' => $rule->description,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            $this->audit->log('violation.approved', $record, ['status' => ViolationRecord::PENDING], [
                'status' => ViolationRecord::APPROVED,
                'offense_number' => $number,
                'rule_id' => $rule->id,
                'action' => $rule->action->name,
                'remarks' => $remarks,
            ]);

            $this->notifyReporter($record, 'Report approved', "{$record->reference_no} was approved as the "
                .\App\Support\Ordinal::format($number).' offense.');

            return $record;
        });
    }

    public function reject(User $admin, ViolationRecord $record, string $remarks): ViolationRecord
    {
        return $this->review($admin, $record, ViolationRecord::REJECTED, $remarks, 'violation.rejected', 'Report rejected');
    }

    public function returnForInfo(User $admin, ViolationRecord $record, string $remarks): ViolationRecord
    {
        return $this->review($admin, $record, ViolationRecord::RETURNED, $remarks, 'violation.returned', 'More information requested');
    }

    /**
     * Admin logs an appeal on behalf of a student against an APPROVED record.
     */
    public function fileAppeal(User $admin, ViolationRecord $record, string $statement): Appeal
    {
        return DB::transaction(function () use ($admin, $record, $statement) {
            $record = ViolationRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

            if ($record->status !== ViolationRecord::APPROVED) {
                throw new WorkflowException('Only approved (verified) records can be appealed.');
            }

            if ($record->appeals()->where('status', Appeal::PENDING)->exists()) {
                throw new WorkflowException('This record already has a pending appeal.');
            }

            $appeal = $record->appeals()->create([
                'filed_by' => $admin->id,
                'statement' => $statement,
                'status' => Appeal::PENDING,
            ]);

            $this->audit->log('appeal.filed', $record, null, ['appeal_id' => $appeal->id]);

            return $appeal;
        });
    }

    /**
     * Decide an appeal. "overturned" voids the record (it stays in history, excluded from
     * offense counts). Nothing is deleted or silently edited.
     */
    public function decideAppeal(User $admin, Appeal $appeal, string $decision, string $remarks): Appeal
    {
        if (! in_array($decision, [Appeal::UPHELD, Appeal::OVERTURNED], true)) {
            throw new WorkflowException('Invalid appeal decision.');
        }

        return DB::transaction(function () use ($admin, $appeal, $decision, $remarks) {
            $appeal = Appeal::query()->whereKey($appeal->id)->lockForUpdate()->firstOrFail();
            if ($appeal->status !== Appeal::PENDING) {
                throw new WorkflowException('This appeal has already been decided.');
            }

            $record = ViolationRecord::query()->whereKey($appeal->violation_record_id)->lockForUpdate()->firstOrFail();

            $appeal->update([
                'status' => $decision,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_remarks' => $remarks,
            ]);

            if ($decision === Appeal::OVERTURNED) {
                $old = ['status' => $record->status, 'offense_number' => $record->offense_number];

                $record->update([
                    'status' => ViolationRecord::VOIDED,
                    'voided_at' => now(),
                    'void_reason' => $remarks,
                ]);

                $this->audit->log('violation.voided_on_appeal', $record, $old, [
                    'status' => ViolationRecord::VOIDED,
                    'appeal_id' => $appeal->id,
                ]);
            }

            $this->audit->log('appeal.decided', $record, ['status' => Appeal::PENDING], [
                'appeal_id' => $appeal->id,
                'decision' => $decision,
            ]);

            $this->notifyReporter($record, 'Appeal decided', "The appeal on {$record->reference_no} was {$decision}.");

            return $appeal;
        });
    }

    // ------------------------------------------------------------------ helpers

    private function review(User $admin, ViolationRecord $record, string $status, string $remarks, string $auditAction, string $title): ViolationRecord
    {
        return DB::transaction(function () use ($admin, $record, $status, $remarks, $auditAction, $title) {
            $record = $this->lockPending($record, $admin);

            $record->update([
                'status' => $status,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            $this->audit->log($auditAction, $record, ['status' => ViolationRecord::PENDING], [
                'status' => $status,
                'remarks' => $remarks,
            ]);

            $this->notifyReporter($record, $title, "{$record->reference_no}: {$remarks}");

            return $record;
        });
    }

    private function lockPending(ViolationRecord $record, User $admin): ViolationRecord
    {
        $record = ViolationRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();

        if ($record->status !== ViolationRecord::PENDING) {
            throw new WorkflowException('Only pending reports can be reviewed. This report is already '.$record->status.'.');
        }

        if ($record->reported_by === $admin->id) {
            throw new WorkflowException('You cannot review a report that you filed yourself.');
        }

        return $record;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string>  $stored  collects paths so they can be removed if the transaction fails
     */
    private function storeEvidence(ViolationRecord $record, array $files, User $user, array &$stored): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Private disk, random file name; the original name is kept for display only.
            $path = $file->store('svms/evidence/'.$record->id, 'local');
            $stored[] = $path;

            $record->evidence()->create([
                'uploaded_by' => $user->id,
                'path' => $path,
                'original_name' => Str::limit(basename((string) $file->getClientOriginalName()), 200, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ]);
        }
    }

    private function notifyAdmins(ViolationRecord $record, string $title, string $message): void
    {
        $admins = User::query()->where('role', User::ROLE_ADMIN)->where('is_active', true)->get();
        Notification::send($admins, new RecordNotification($title, $message, $record->id));
    }

    private function notifyReporter(ViolationRecord $record, string $title, string $message): void
    {
        $reporter = User::find($record->reported_by);
        $reporter?->notify(new RecordNotification($title, $message, $record->id));
    }
}
