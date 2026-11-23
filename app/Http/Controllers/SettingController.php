<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['settings' => Setting::allCached()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'council_name' => ['required', 'string', 'max:255'],
            'report_footer' => ['nullable', 'string', 'max:500'],
            'dpo_contact' => ['nullable', 'string', 'max:255'],
            'retention_policy' => ['nullable', 'string', 'max:3000'],
        ]);

        $before = Setting::allCached();
        $old = [];
        $new = [];

        foreach ($data as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
            Setting::set($key, $value);
        }

        if ($new !== []) {
            $audit->log('settings.updated', null, $old, $new);
        }

        return back()->with('success', 'Settings saved.');
    }
}
