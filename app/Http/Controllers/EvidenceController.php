<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Evidence lives on the private disk (never in /public). The only way to read a file is
 * through this authorised, audited endpoint.
 */
class EvidenceController extends Controller
{
    public function show(EvidenceFile $evidence, AuditLogger $audit): StreamedResponse
    {
        $record = $evidence->record;
        Gate::authorize('view', $record);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($evidence->path), 404);

        $audit->log('evidence.downloaded', $record, null, ['file' => $evidence->original_name]);

        // Always as an attachment: uploaded content is never rendered inside the app.
        return $disk->download($evidence->path, $evidence->original_name);
    }
}
