<?php

namespace App\Mail;

use App\Models\NotificationLog;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** Every customer / admin email: the branded Blade layout around the rendered template body and optional blocks. */
class BrandedMessage extends Mailable
{
    public function __construct(public NotificationLog $log) {}

    public function envelope(): Envelope
    {
        $reply = MessagingSettings::get('mail.reply_to');
        $from = MessagingSettings::get('mail.from_address');

        return new Envelope(
            from: $from ? new Address($from, MessagingSettings::get('mail.from_name') ?? config('mail.from.name')) : null,
            subject: (string) ($this->log->subject ?: config('messaging.brand.name')),
            replyTo: $reply ? [new Address($reply, config('messaging.mail.reply_to_name'))] : [],
            tags: [$this->log->event],
            metadata: ['log_id' => (string) $this->log->id],
        );
    }

    public function headers(): Headers
    {
        $unsub = $this->log->meta['unsubscribe'] ?? null;

        return new Headers(text: $unsub ? ['List-Unsubscribe' => '<'.$unsub.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'] : []);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.branded',
            text: 'emails.branded-text',
            with: self::viewData($this->log->subject, $this->log->body, $this->log->meta ?? [], $this->log->locale ?: 'en'),
        );
    }

    /** Shared with the admin preview. */
    public static function viewData(?string $subject, string $body, array $meta, string $locale = 'en'): array
    {
        $brand = config('messaging.brand');

        return [
            'subject' => (string) $subject,
            'paragraphs' => array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $body)), fn ($p) => $p !== '')),
            'body' => $body,
            'blocks' => $meta['blocks'] ?? [],
            'cta' => $meta['cta'] ?? null,
            'image' => $meta['image'] ?? null,
            'marketing' => (bool) ($meta['marketing'] ?? false),
            'admin' => (bool) ($meta['admin'] ?? false),
            'unsubscribe' => $meta['unsubscribe'] ?? null,
            'locale' => $locale,
            'brand' => $brand['name'] ?? 'Skydrop Retail',
            'logo' => $brand['logo_url'] ?: rtrim((string) config('app.url'), '/').'/images/skydrop-retail-logo.png',
            'supportEmail' => MessagingSettings::get('mail.reply_to') ?: ($brand['support_email'] ?? null),
            'supportPhone' => $brand['support_phone'] ?? null,
            'address' => $brand['address'] ?? null,
            'webUrl' => rtrim((string) config('retail.web_url'), '/'),
        ];
    }
}
