<?php

namespace App\Casts;

use App\Models\Setting;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * JSON value cast for the settings table that encrypts secret keys at rest (Setting::isSecret()).
 * Stored form for secrets: "enc:" + Crypt::encryptString(json). Plain JSON (older rows) is still read,
 * and is encrypted the next time the setting is saved.
 */
class SettingValue implements CastsAttributes
{
    public const PREFIX = 'enc:';

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        $raw = (string) $value;
        if (str_starts_with($raw, self::PREFIX)) {
            try {
                $raw = Crypt::decryptString(substr($raw, strlen(self::PREFIX)));
            } catch (Throwable) {
                return null; // APP_KEY rotated without re-encrypting: treat as unset
            }
        }

        return json_decode($raw, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Setting::isSecret((string) ($attributes['key'] ?? $model->getAttribute('key') ?? '')) && $value !== ''
            ? self::PREFIX.Crypt::encryptString($json)
            : $json;
    }
}
