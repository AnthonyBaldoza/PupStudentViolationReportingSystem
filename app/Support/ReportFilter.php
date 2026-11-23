<?php

namespace App\Support;

use App\Models\Student;
use App\Models\ViolationRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * One place that turns report-page filters into a query, shared by the on-screen report,
 * the printable view, the CSV export and the PDF export so they can never disagree.
 */
final class ReportFilter
{
    /** @param array<string, mixed> $filters validated filter values */
    public function __construct(public readonly array $filters)
    {
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:violation_categories,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level' => ['nullable', 'integer', 'between:1,6'],
            'status' => ['nullable', Rule::in(ViolationRecord::STATUSES)],
            'offense_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]));
    }

    /** Filters only: no eager loading or ordering (safe for aggregates). */
    public function baseQuery(): Builder
    {
        $f = $this->filters;

        return ViolationRecord::query()
            ->when($f['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('incident_date', '>=', $v))
            ->when($f['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('incident_date', '<=', $v))
            // Student filters use sub-selects that include archived students.
            ->when($f['q'] ?? null, fn (Builder $q, $v) => $q->whereIn('student_id', Student::withTrashed()->search($v)->select('id')))
            ->when($f['course_id'] ?? null, fn (Builder $q, $v) => $q->whereIn('student_id', Student::withTrashed()->where('course_id', $v)->select('id')))
            ->when($f['year_level'] ?? null, fn (Builder $q, $v) => $q->whereIn('student_id', Student::withTrashed()->where('year_level', $v)->select('id')))
            ->when($f['category_id'] ?? null, fn (Builder $q, $v) => $q->where('violation_category_id', $v))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($f['offense_number'] ?? null, fn (Builder $q, $v) => $q->where('offense_number', $v));
    }

    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['student.course', 'student.section', 'category', 'reporter', 'reviewer'])
            ->orderByDesc('incident_date')
            ->orderByDesc('id');
    }

    /**
     * @return array{total: int, by_status: Collection, top_categories: Collection, range: string}
     */
    public function summary(): array
    {
        $byStatus = $this->baseQuery()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $top = $this->baseQuery()
            ->select('category_name', DB::raw('COUNT(*) AS total'))
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'category_name');

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus,
            'top_categories' => $top,
            'range' => $this->rangeLabel(),
        ];
    }

    public function rangeLabel(): string
    {
        $from = $this->filters['date_from'] ?? null;
        $to = $this->filters['date_to'] ?? null;

        return match (true) {
            $from && $to => Carbon::parse($from)->format('M d, Y').' – '.Carbon::parse($to)->format('M d, Y'),
            (bool) $from => 'From '.Carbon::parse($from)->format('M d, Y'),
            (bool) $to => 'Up to '.Carbon::parse($to)->format('M d, Y'),
            default => 'All dates',
        };
    }
}
