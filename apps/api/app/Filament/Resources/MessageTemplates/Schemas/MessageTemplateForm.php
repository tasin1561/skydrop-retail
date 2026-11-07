<?php

namespace App\Filament\Resources\MessageTemplates\Schemas;

use App\Mail\BrandedMessage;
use App\Models\MessageTemplate;
use App\Services\Messaging\Events;
use App\Services\Messaging\SmsSegments;
use App\Services\Messaging\TemplateRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(5)
            ->components([
                Section::make('Template')
                    ->columnSpan(3)
                    ->columns(3)
                    ->schema([
                        Select::make('key')->label('Event')->options(Events::labels())->required()->searchable()->live()->columnSpan(3)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('channel', $get('channel'))->where('locale', $get('locale'))),
                        Select::make('channel')->options(MessageTemplate::CHANNELS)->required()->live(),
                        Select::make('locale')->label('Language')->options(MessageTemplate::LOCALES)->required()->default('en')->live(),
                        Toggle::make('active')->default(true)->inline(false),
                        TextInput::make('subject')->label(fn (Get $get) => $get('channel') === 'push' ? 'Title' : 'Subject')->maxLength(190)->columnSpan(3)
                            ->live(debounce: 500)->hidden(fn (Get $get) => $get('channel') === 'sms'),
                        Textarea::make('body')->required()->rows(fn (Get $get) => $get('channel') === 'email' ? 10 : 4)->columnSpan(3)->live(debounce: 400)
                            ->helperText(fn (Get $get) => $get('channel') === 'sms' ? new HtmlString(self::segmentHint((string) $get('key'), (string) $get('body'))) : 'Blank line = new paragraph.'),
                        Html::make(fn (Get $get) => new HtmlString('<div style="font-size:.8rem;opacity:.75;line-height:1.5">Placeholders: '
                            .collect(array_keys(Events::get((string) $get('key'))['sample'] ?? []))->map(fn ($k) => '<code>{{'.e($k).'}}</code>')->implode(' ')
                            .'<br>Conditional text: <code>{{#due}}…{{/due}}</code> shows only when set, <code>{{^due}}…{{/due}}</code> only when empty.</div>'))->columnSpan(3),
                    ]),
                Section::make('Live preview')
                    ->description('Rendered with sample data.')
                    ->columnSpan(2)
                    ->schema([
                        Html::make(fn (Get $get) => self::preview((string) $get('key'), (string) $get('channel'), (string) $get('locale'), $get('subject'), (string) $get('body'))),
                    ]),
            ]);
    }

    public static function segmentHint(string $key, string $body): string
    {
        $rendered = app(TemplateRenderer::class)->string($body, Events::get($key)['sample'] ?? []);
        $a = SmsSegments::analyze($rendered);
        $color = $a['segments'] <= 1 ? '#059669' : ($a['segments'] === 2 ? '#d97706' : '#e11d48');

        return '<span style="color:'.$color.';font-weight:600">'.e(SmsSegments::describe($rendered)).'</span> with sample data. '
            .($a['encoding'] === 'UCS-2' ? 'Unicode (Bangla): 70 per SMS, 67 per part.' : 'GSM: 160 per SMS, 153 per part.');
    }

    public static function preview(string $key, string $channel, string $locale, ?string $subject, string $body): HtmlString
    {
        $r = app(TemplateRenderer::class);
        $data = (Events::get($key)['sample'] ?? []) + ['name' => 'Rahim'];
        $s = $subject ? $r->string($subject, $data) : null;
        $b = $r->string($body, $data);

        if ($channel === 'email') {
            $html = view('emails.branded', BrandedMessage::viewData($s, $b, ['cta' => ['label' => 'View details', 'url' => '#']], $locale ?: 'en'))->render();

            return new HtmlString('<iframe title="Email preview" sandbox srcdoc="'.e($html).'" style="width:100%;height:560px;border:1px solid rgba(127,127,127,.25);border-radius:.75rem;background:#f2f5f9"></iframe>');
        }
        if ($channel === 'push') {
            return new HtmlString('<div style="display:flex;gap:.6rem;padding:.75rem;border-radius:1rem;background:rgba(127,127,127,.1);align-items:flex-start">'
                .'<img src="'.e(asset('images/skydrop-retail-logo.png')).'" style="width:2.2rem;height:2.2rem;border-radius:.5rem" alt="">'
                .'<div style="min-width:0"><div style="font-size:.7rem;opacity:.6">Skydrop Retail · now</div><div style="font-weight:700">'.e((string) $s).'</div>'
                .'<div style="font-size:.9rem;opacity:.85">'.nl2br(e($b)).'</div></div></div>');
        }

        return new HtmlString('<div style="max-width:20rem;padding:.7rem .9rem;border-radius:1.1rem 1.1rem 1.1rem .3rem;background:rgba(127,127,127,.14);font-size:.9rem;line-height:1.45;white-space:pre-wrap;word-break:break-word">'
            .e($b).'</div><div style="margin-top:.5rem;font-size:.8rem;opacity:.75">'.e(SmsSegments::describe($b)).'</div>');
    }
}
