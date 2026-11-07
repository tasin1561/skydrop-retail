<?php

namespace App\Filament\Resources\RestrictedRules;

use App\Filament\Resources\RestrictedRules\Pages\CreateRestrictedRule;
use App\Filament\Resources\RestrictedRules\Pages\EditRestrictedRule;
use App\Filament\Resources\RestrictedRules\Pages\ListRestrictedRules;
use App\Filament\Resources\RestrictedRules\Schemas\RestrictedRuleForm;
use App\Filament\Resources\RestrictedRules\Tables\RestrictedRulesTable;
use App\Filament\Support\Admin;
use App\Models\RestrictedRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RestrictedRuleResource extends Resource
{
    protected static ?string $model = RestrictedRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'message';

    protected static ?string $navigationLabel = 'Shipping restrictions';

    public static function form(Schema $schema): Schema
    {
        return RestrictedRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestrictedRulesTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record); // RBAC: {slug}.ability via Gate
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestrictedRules::route('/'),
            'create' => CreateRestrictedRule::route('/create'),
            'edit' => EditRestrictedRule::route('/{record}/edit'),
        ];
    }
}
