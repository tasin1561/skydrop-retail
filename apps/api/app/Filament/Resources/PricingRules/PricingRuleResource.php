<?php

namespace App\Filament\Resources\PricingRules;

use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\PricingRules\Pages\ListPricingRules;
use App\Filament\Resources\PricingRules\Schemas\PricingRuleForm;
use App\Filament\Resources\PricingRules\Tables\PricingRulesTable;
use App\Filament\Support\Admin;
use App\Models\PricingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'category';

    public static function form(Schema $schema): Schema
    {
        return PricingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingRulesTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record); // RBAC: {slug}.ability via Gate
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricingRules::route('/'),
            'create' => CreatePricingRule::route('/create'),
            'edit' => EditPricingRule::route('/{record}/edit'),
        ];
    }
}
