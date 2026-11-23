<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /** Never written to the audit trail. */
    private const SENSITIVE = ['password', 'remember_token'];

    public function log(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ? $this->scrub($old) : null,
            'new_values' => $new ? $this->scrub($new) : null,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }

    /**
     * Save a (filled but unsaved) model and record exactly which fields changed.
     */
    public function saveWithAudit(Model $model, string $action): void
    {
        $new = $model->getDirty();
        $old = array_intersect_key($model->getOriginal(), $new);

        $model->save();

        if ($new !== []) {
            $this->log($action, $model, $old, $new);
        }
    }

    private function scrub(array $values): array
    {
        return array_diff_key($values, array_flip(self::SENSITIVE));
    }
}
