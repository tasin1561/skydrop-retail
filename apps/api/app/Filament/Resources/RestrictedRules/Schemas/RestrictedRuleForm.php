<?php

namespace App\Filament\Resources\RestrictedRules\Schemas;

use App\Models\RestrictedRule;
use App\Services\Cms\RestrictionService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RestrictedRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('type')->options(RestrictedRule::TYPES)->default('keyword')->required(),
                Select::make('action')->options(RestrictedRule::ACTIONS)->default('warn')->required()->live(),
                Textarea::make('pattern')->required()->rows(3)->maxLength(1000)->columnSpanFull()
                    ->helperText('Comma-separated words or phrases (matched as whole words, any case), or a regular expression like /\b(drones?|quadcopters?)\b/i. Brand rules match the whole brand name.')
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        if (! RestrictionService::validPattern((string) $value)) {
                            $fail('This regular expression is not valid.');
                        }
                    }),
                Textarea::make('message')->required()->rows(2)->maxLength(500)->columnSpanFull()
                    ->helperText('Shown to shoppers on the product page, cart and checkout. Say why, in plain words.'),
                TextInput::make('surcharge_bdt')->label('Surcharge per unit')->prefix('৳')->numeric()->minValue(0)->default(0)
                    ->visible(fn (Get $get) => $get('action') === 'surcharge'),
                TextInput::make('sort')->numeric()->default(0),
                Toggle::make('active')->default(true),
            ]),
        ]);
    }
}
