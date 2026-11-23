<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', str_replace(['%', '_'], '', $v).'%'))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $f,
        ]);
    }
}
