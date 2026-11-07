<?php

namespace App\Services\Commerce\Sms;

use App\Services\Messaging\MessagingSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractGateway implements SmsGateway
{
    public function send(string $phone, string $message): bool
    {
        return $this->deliver($phone, $message)->ok;
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return MessagingSettings::get('sms.'.$this->name().'.'.$key, $default);
    }

    protected function failed(string $error, mixed $body = null, bool $retryable = true): SmsResult
    {
        Log::warning('[sms:'.$this->name().'] '.$error, $body !== null ? ['body' => Str::limit(is_string($body) ? $body : json_encode($body), 300)] : []);

        return SmsResult::fail($this->name(), $error, $retryable);
    }
}
