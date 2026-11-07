<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Filament\Support\Admin;
use App\Models\Banner;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BannerForm
{
    public static function stores(): array
    {
        return ['all' => 'All stores'] + Admin::MARKETS;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Content')->columnSpan(2)->columns(2)->schema([
                TextInput::make('title')->required()->maxLength(160)->live(onBlur: true)->columnSpanFull(),
                TextInput::make('subtitle')->maxLength(300)->live(onBlur: true)->columnSpanFull(),
                FileUpload::make('image_path')->label('Upload image')->image()->disk('public')->directory('banners')->visibility('public')
                    ->maxSize(4096)->imageEditor()->helperText('Hero: 1600×640 (5:2). Strip: 1600×300. Category: 600×600. Upload wins over the URL.'),
                TextInput::make('image_url')->label('…or image URL')->url()->maxLength(1000)->live(onBlur: true),
                TextInput::make('link')->label('Link')->maxLength(1000)->placeholder('/store/amazon or /search?q=saree')
                    ->helperText('Relative storefront path or full https URL.'),
                TextInput::make('cta')->label('Button text')->maxLength(40)->placeholder('Shop now')->live(onBlur: true),
            ]),
            Section::make('Placement and schedule')->columnSpan(1)->schema([
                Select::make('store')->options(self::stores())->default('all')->required(),
                Select::make('placement')->options(Banner::PLACEMENTS)->default('hero')->required(),
                DateTimePicker::make('starts_at')->label('Starts')->seconds(false),
                DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->after('starts_at'),
                TextInput::make('sort')->numeric()->default(0)->helperText('Lower shows first.'),
                Toggle::make('active')->default(true),
            ]),
            Section::make('Preview')->columnSpanFull()->schema([
                Html::make(fn (Get $get, ?Banner $record) => self::preview($get('title'), $get('subtitle'), $get('cta'), $get('image_url') ?: $record?->imageUrl())),
            ]),
        ]);
    }

    public static function preview(?string $title, ?string $subtitle, ?string $cta, ?string $image): HtmlString
    {
        $bg = $image ? 'background-image:linear-gradient(90deg,rgba(11,16,32,.75),rgba(11,16,32,.1)),url('.e($image).');background-size:cover;background-position:center'
            : 'background:linear-gradient(120deg,#0b6bcb,#38bdf8)';

        return new HtmlString('<div style="'.$bg.';color:#fff;border-radius:1rem;padding:2rem;min-height:10rem;display:flex;flex-direction:column;justify-content:flex-end;gap:.4rem;max-width:56rem">'
            .'<strong style="font-size:1.5rem;line-height:1.2">'.e($title ?: 'Banner title').'</strong>'
            .($subtitle ? '<span style="opacity:.9">'.e($subtitle).'</span>' : '')
            .'<span style="margin-top:.5rem;align-self:flex-start;background:#fff;color:#0f172a;border-radius:999px;padding:.4rem 1rem;font-weight:600;font-size:.85rem">'.e($cta ?: 'Shop now').'</span></div>');
    }
}
