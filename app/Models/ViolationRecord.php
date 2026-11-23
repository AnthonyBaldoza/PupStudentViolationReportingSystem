<?php

namespace App\Models;

use App\Support\Ordinal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationRecord extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const RETURNED = 'returned';
    public const VOIDED = 'voided';
    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED, self::RETURNED, self::VOIDED];

    protected $fillable = [
        'reference_no',
        'student_id',
        'violation_category_id',
        'category_name',
        'reported_by',
        'incident_date',
        'incident_time',
        'location',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'offense_number',
        'offense_rule_id',
        'action_name',
        'action_description',
        'rule_description',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'reviewed_at' => 'datetime',
            'voided_at' => 'datetime',
            'offense_number' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ViolationCategory::class, 'violation_category_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OffenseRule::class, 'offense_rule_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(Appeal::class)->latest();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::APPROVED);
    }

    public function getOffenseLabelAttribute(): ?string
    {
        return $this->offense_number ? Ordinal::format($this->offense_number).' offense' : null;
    }
}
