<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $table = 'notifications_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'segments' => 'integer', 'attempts' => 'integer', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Recipient with the middle hidden (phones) or the local part shortened (emails), for the admin view. */
    public function maskedTo(): string
    {
        $to = (string) $this->to;
        if (str_contains($to, '@')) {
            [$local, $domain] = explode('@', $to, 2);

            return mb_substr($local, 0, 2).str_repeat('•', max(1, min(6, mb_strlen($local) - 2))).'@'.$domain;
        }
        if (preg_match('/^\+?\d{8,}$/', $to)) {
            return substr($to, 0, 6).'•••••'.substr($to, -3);
        }

        return $to;
    }
}
