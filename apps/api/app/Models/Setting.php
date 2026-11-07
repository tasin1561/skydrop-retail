<?php

namespace App\Models;

use App\Casts\SettingValue;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => SettingValue::class]; // JSON; secret keys encrypted at rest
    }

    /** Last key segment patterns that hold credentials (encrypted at rest, masked in admin forms). */
    public const SECRET_PATTERNS = ['/token/i', '/secret/i', '/passw(or)?d/i', '/(^|_)key$/i', '/private/i'];

    public static function isSecret(string $key): bool
    {
        if (str_starts_with($key, 'features.')) {
            return false;
        }
        $last = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
        foreach (self::SECRET_PATTERNS as $re) {
            if (preg_match($re, $last)) {
                return true;
            }
        }

        return false;
    }

    /** "••••abcd" for showing a stored secret without revealing it. */
    public static function mask(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (string) (is_scalar($value) ? $value : json_encode($value));

        return '••••'.(mb_strlen($v) > 8 ? mb_substr($v, -4) : '');
    }

    public static function isMasked(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '••••');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)?->value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
