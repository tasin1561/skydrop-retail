<?php

namespace App\Filament\Resources\SupportRequests;

use App\Filament\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Filament\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestForm;
use App\Filament\Resources\SupportRequests\Schemas\SupportRequestInfolist;
use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupportRequestResource extends Resource
{
    protected static ?string $slug = 'support';

    protected static ?string $model = SupportRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Support';

    public static function form(Schema $schema): Schema
    {
        return SupportRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record); // RBAC: {slug}.ability via Gate
    }

    public static function getNavigationBadge(): ?string
    {
        $n = SupportRequest::query()->whereIn('status', ['open', 'in_review'])->count();

        return $n ? (string) $n : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportRequests::route('/'),
            'view' => ViewSupportRequest::route('/{record}'),
            'edit' => EditSupportRequest::route('/{record}/edit'),
        ];
    }
}
