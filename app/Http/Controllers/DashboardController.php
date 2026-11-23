<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\ViolationRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Admins see everything; officers see figures for their own reports only.
        $scope = fn () => ViolationRecord::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('reported_by', $user->id));

        $counts = $scope()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'students' => Student::count(),
            'reports' => (int) $counts->sum(),
            'pending' => (int) ($counts[ViolationRecord::PENDING] ?? 0),
            'approved' => (int) ($counts[ViolationRecord::APPROVED] ?? 0),
            'rejected' => (int) ($counts[ViolationRecord::REJECTED] ?? 0),
            'returned' => (int) ($counts[ViolationRecord::RETURNED] ?? 0),
        ];

        // Monthly statistics for the last 12 months (by incident date).
        $start = now()->startOfMonth()->subMonths(11);
        $rows = $scope()
            ->where('incident_date', '>=', $start->toDateString())
            ->get(['incident_date', 'status']);

        $monthly = ['labels' => [], 'approved' => [], 'pending' => [], 'rejected' => []];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $inMonth = $rows->filter(fn ($r) => $r->incident_date->format('Y-m') === $month->format('Y-m'));

            $monthly['labels'][] = $month->format('M Y');
            $monthly['approved'][] = $inMonth->where('status', ViolationRecord::APPROVED)->count();
            $monthly['pending'][] = $inMonth->where('status', ViolationRecord::PENDING)->count();
            $monthly['rejected'][] = $inMonth->where('status', ViolationRecord::REJECTED)->count();
        }

        // Most common violation categories (approved records only).
        $top = $scope()
            ->where('status', ViolationRecord::APPROVED)
            ->select('category_name', DB::raw('COUNT(*) AS total'))
            ->groupBy('category_name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $topCategories = [
            'labels' => $top->pluck('category_name')->all(),
            'values' => $top->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];

        $recent = $scope()
            ->with(['student', 'category'])
            ->latest()
            ->latest('id')
            ->limit(8)
            ->get();

        return view('dashboard', compact('stats', 'monthly', 'topCategories', 'recent'));
    }
}
