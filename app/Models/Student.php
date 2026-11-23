<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes; // soft delete == "archived"

    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const GRADUATED = 'graduated';
    public const STATUSES = [self::ACTIVE, self::INACTIVE, self::GRADUATED];

    protected $fillable = [
        'student_no',
        'last_name',
        'first_name',
        'middle_name',
        'course_id',
        'section_id',
        'year_level',
        'status',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ViolationRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';

        return $this->last_name.', '.$this->first_name.$middle;
    }

    /**
     * Search by student ID or name (portable LOWER/LIKE, works on PostgreSQL and SQLite).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim(str_replace(['%', '_'], '', (string) $term));
        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $w) use ($like) {
            $w->whereRaw('LOWER(student_no) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw("LOWER(last_name || ', ' || first_name) LIKE ?", [$like])
                ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like]);
        });
    }
}
