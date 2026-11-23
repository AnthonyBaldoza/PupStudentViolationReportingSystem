<?php

namespace App\Models;

use App\Support\Ordinal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffenseRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'violation_category_id',
        'offense_number',
        'is_succeeding',
        'description',
        'disciplinary_action_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'offense_number' => 'integer',
            'is_succeeding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ViolationCategory::class, 'violation_category_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryAction::class, 'disciplinary_action_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->is_succeeding
            ? Ordinal::format($this->offense_number).' and succeeding offenses'
            : Ordinal::format($this->offense_number).' offense';
    }
}
