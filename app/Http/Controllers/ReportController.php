<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ViolationCategory;
use App\Models\ViolationRecord;
use App\Services\AuditLogger;
use App\Support\ReportFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filter = ReportFilter::fromRequest($request);

        return view('reports.index', [
            'records' => $filter->query()->paginate(20)->withQueryString(),
            'summary' => $filter->summary(),
            'filters' => $filter->filters,
            'categories' => ViolationCategory::orderBy('name')->get(),
            'courses' => Course::orderBy('code')->get(),
        ]);
    }

    public function print(Request $request, AuditLogger $audit): View
    {
        $filter = ReportFilter::fromRequest($request);
        $audit->log('report.printed', null, null, ['filters' => $filter->filters]);

        return view('reports.print', $this->exportData($filter));
    }

    public function csv(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filter = ReportFilter::fromRequest($request);
        $records = $filter->query()->get();
        $audit->log('report.exported', null, null, ['format' => 'csv', 'rows' => $records->count(), 'filters' => $filter->filters]);

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly

            fputcsv($out, [
                'Reference', 'Student ID', 'Student', 'Course', 'Year', 'Category', 'Incident date', 'Incident time',
                'Location', 'Status', 'Offense no.', 'Disciplinary action', 'Reported by', 'Reviewed by', 'Reviewed at',
            ], ',', '"', '\\');

            foreach ($records as $r) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $r->reference_no,
                    $r->student?->student_no,
                    $r->student?->full_name,
                    $r->student?->course?->code,
                    $r->student?->year_level,
                    $r->category_name,
                    $r->incident_date?->format('Y-m-d'),
                    $r->incident_time,
                    $r->location,
                    $r->status,
                    $r->offense_number,
                    $r->action_name,
                    $r->reporter?->name,
                    $r->reviewer?->name,
                    $r->reviewed_at?->format('Y-m-d H:i'),
                ]), ',', '"', '\\');
            }

            fclose($out);
        }, 'violation-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request, AuditLogger $audit): mixed
    {
        // barryvdh/laravel-dompdf is installed with: composer require barryvdh/laravel-dompdf
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'PDF export needs the dompdf package. Run: composer require barryvdh/laravel-dompdf');
        }

        $filter = ReportFilter::fromRequest($request);
        $data = $this->exportData($filter);
        $audit->log('report.exported', null, null, ['format' => 'pdf', 'rows' => $data['records']->count(), 'filters' => $filter->filters]);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('violation-report-'.now()->format('Ymd-His').'.pdf');
    }

    private function exportData(ReportFilter $filter): array
    {
        return [
            'records' => $filter->query()->get(),
            'summary' => $filter->summary(),
            'filters' => $filter->filters,
            'categoryName' => ($filter->filters['category_id'] ?? null) ? ViolationCategory::find($filter->filters['category_id'])?->name : null,
            'courseCode' => ($filter->filters['course_id'] ?? null) ? Course::find($filter->filters['course_id'])?->code : null,
        ];
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
