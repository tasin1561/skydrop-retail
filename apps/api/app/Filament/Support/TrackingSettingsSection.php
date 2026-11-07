<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;

/** "Tracking" section of the Settings page (keys tracking.* in Admin::SETTINGS, fallback config/retail.php). */
class TrackingSettingsSection
{
    public static function make(): Section
    {
        $secret = fn (string $key, string $label) => TextInput::make($key)->label($label)->password()->revealable()->maxLength(500);

        return Section::make('Tracking')
            ->description('Ad pixels and server-side events. Browser pixels load only after the shopper accepts cookies; '
                .'server events reuse the browser event_id so Meta and TikTok count each event once.')
            ->columnSpanFull()
            ->collapsible()
            ->columns(3)
            ->schema([
                Select::make('tracking.driver')->label('Server-side driver')
                    ->options(['off' => 'Off', 'delivro' => 'Delivro (delivro.app)', 'direct' => 'Direct (Meta CAPI, GA4 MP, TikTok)', 'both' => 'Both'])
                    ->required(),
                Select::make('tracking.purchase_on')->label('Send Purchase when')
                    ->options(['paid' => 'Payment received (or advance paid)', 'placed' => 'Order placed', 'delivered' => 'Delivered (Skydrop)'])
                    ->required(),
                Toggle::make('tracking.send_delivered')->label('Also send a custom "Delivered" event')->inline(false),

                Fieldset::make('Meta')->columns(3)->columnSpanFull()->schema([
                    TextInput::make('tracking.meta.pixel_id')->label('Pixel ID')->maxLength(40),
                    $secret('tracking.meta.access_token', 'Conversions API token'),
                    TextInput::make('tracking.meta.test_event_code')->label('Test event code')->maxLength(40)
                        ->helperText('From Events Manager → Test events. Clear it when going live.'),
                ]),
                Fieldset::make('Google')->columns(3)->columnSpanFull()->schema([
                    TextInput::make('tracking.ga4.measurement_id')->label('GA4 measurement ID')->placeholder('G-XXXXXXX')->maxLength(40),
                    $secret('tracking.ga4.api_secret', 'GA4 Measurement Protocol API secret'),
                    TextInput::make('tracking.gtm_id')->label('GTM container ID (optional)')->placeholder('GTM-XXXXXX')->maxLength(40),
                ]),
                Fieldset::make('TikTok')->columns(3)->columnSpanFull()->schema([
                    TextInput::make('tracking.tiktok.pixel_id')->label('Pixel ID')->maxLength(40),
                    $secret('tracking.tiktok.access_token', 'Events API access token'),
                    TextInput::make('tracking.tiktok.test_event_code')->label('Test event code')->maxLength(40),
                ]),
                Fieldset::make('Delivro')->columns(2)->columnSpanFull()->schema([
                    TextInput::make('tracking.delivro.endpoint')->label('Ingest endpoint')->url()->placeholder('https://delivro.app/…')->maxLength(300),
                    $secret('tracking.delivro.key', 'API key'),
                ]),
                $secret('tracking.feed_key', 'Product feed key')
                    ->helperText('Optional. When set, feeds need ?key=… : /api/v1/feeds/meta.csv and /api/v1/feeds/google.xml'),
            ]);
    }
}
