<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const CACHE_KEY = 'svms.settings';

    /** Neutral placeholders only: the school fills these in. */
    public const DEFAULTS = [
        'school_name' => 'Your School Name',
        'council_name' => 'Student Council',
        'report_footer' => 'Confidential. For authorized school personnel only.',
        'dpo_contact' => '',
        'retention_policy' => '',
    ];

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function allCached(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 600, fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::allCached()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }
}
