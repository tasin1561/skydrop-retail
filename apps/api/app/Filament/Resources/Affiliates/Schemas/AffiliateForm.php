<?php

namespace App\Filament\Resources\Affiliates\Schemas;

use App\Models\Affiliate;
use App\Services\Growth\AffiliateService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AffiliateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Affiliate')->columns(2)->columnSpanFull()->schema([
                TextInput::make('code')->required()->maxLength(16)->regex(AffiliateService::CODE_RULE.'i')
                    ->unique(ignoreRecord: true)->helperText('Shared as ?ref=CODE. Changing it breaks links already shared.'),
                Select::make('status')->options(Affiliate::STATUSES)->required(),
                TextInput::make('default_rate')->label('Own commission rate')->numeric()->minValue(0)->maxValue(50)->suffix('%')
                    ->helperText('Leave empty to use the category rates from Growth settings.'),
                Select::make('payout_method')->options(Affiliate::PAYOUT_METHODS),
                TextInput::make('payout_number')->label('bKash / Nagad number')->maxLength(20),
                Textarea::make('notes')->rows(4)->columnSpanFull(),
            ]),
        ]);
    }
}
