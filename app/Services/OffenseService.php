<?php

namespace App\Services;

use App\Models\OffenseRule;
use App\Models\Student;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use Illuminate\Support\Collection;

/**
 * Offense counting. THE core business rule of the system:
 *
 *  - counts are per student AND per category (categories are never combined)
 *  - only APPROVED records count (pending, rejected, returned and voided never do)
 *  - the number is assigned at approval time and then snapshotted on the record
 */
class OffenseService
{
    public function approvedCount(int $studentId, int $categoryId): int
    {
        return ViolationRecord::query()
            ->where('student_id', $studentId)
            ->where('violation_category_id', $categoryId)
            ->where('status', ViolationRecord::APPROVED)
            ->count();
    }

    /**
     * Next offense number = highest APPROVED number + 1 (== count + 1 in the normal case).
     * Using the highest number (not the count) means a number is never handed out twice
     * even if an earlier record was later voided on appeal, and no verified record has
     * to be renumbered. Call this only inside a transaction that locked the student row.
     */
    public function nextOffenseNumber(int $studentId, int $categoryId): int
    {
        $highest = ViolationRecord::query()
            ->where('student_id', $studentId)
            ->where('violation_category_id', $categoryId)
            ->where('status', ViolationRecord::APPROVED)
            ->max('offense_number');

        return ((int) $highest) + 1;
    }

    /**
     * The configured rule for the Nth offense: an exact rule for N wins; otherwise the
     * highest "N-and-succeeding" rule that starts at or below N. Null if nothing is configured.
     */
    public function resolveRule(int $categoryId, int $offenseNumber): ?OffenseRule
    {
        $exact = OffenseRule::query()
            ->with('action')
            ->where('violation_category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_succeeding', false)
            ->where('offense_number', $offenseNumber)
            ->first();

        if ($exact) {
            return $exact;
        }

        return OffenseRule::query()
            ->with('action')
            ->where('violation_category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_succeeding', true)
            ->where('offense_number', '<=', $offenseNumber)
            ->orderByDesc('offense_number')
            ->first();
    }

    /**
     * Per-category summary for a student's profile (approved records only).
     *
     * @return Collection<int, array{category: ViolationCategory, count: int, highest: int, next: int}>
     */
    public function summaryForStudent(Student $student): Collection
    {
        $rows = ViolationRecord::query()
            ->where('student_id', $student->id)
            ->where('status', ViolationRecord::APPROVED)
            ->selectRaw('violation_category_id, COUNT(*) AS total, MAX(offense_number) AS highest')
            ->groupBy('violation_category_id')
            ->get()
            ->keyBy('violation_category_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        return ViolationCategory::query()
            ->whereIn('id', $rows->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (ViolationCategory $category) => [
                'category' => $category,
                'count' => (int) $rows[$category->id]->total,
                'highest' => (int) $rows[$category->id]->highest,
                'next' => (int) $rows[$category->id]->highest + 1,
            ]);
    }
}
