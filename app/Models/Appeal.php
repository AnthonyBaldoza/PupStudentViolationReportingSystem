<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appeal extends Model
{
    public const PENDING = 'pending';
    public const UPHELD = 'upheld';         // original record stands
    public const OVERTURNED = 'overturned'; // record is voided (kept for history)

    protected $fillable = [
        'violation_record_id',
        'filed_by',
        'statement',
        'status',
        'decided_by',
        'decided_at',
        'decision_remarks',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ViolationRecord::class, 'violation_record_id');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
