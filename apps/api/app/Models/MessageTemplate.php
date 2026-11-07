<?php

namespace App\Models;

use App\Services\Messaging\TemplateRenderer;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    public const CHANNELS = ['sms' => 'SMS', 'email' => 'Email', 'push' => 'Push / in-app'];

    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(TemplateRenderer::class)->flush());
        static::deleted(fn () => app(TemplateRenderer::class)->flush());
    }
}
