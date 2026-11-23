<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(OffenseRule::class)->orderBy('offense_number');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ViolationRecord::class);
    }
}
