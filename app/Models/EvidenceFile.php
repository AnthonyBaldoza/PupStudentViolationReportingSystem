<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceFile extends Model
{
    protected $fillable = [
        'violation_record_id',
        'uploaded_by',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(ViolationRecord::class, 'violation_record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024), 0).' KB';
    }
}
