<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Concerns\HasResourcePermissions;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use App\Support\Rbac\PermissionPreview;
use App\Support\Rbac\RoleGuard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Staff accounts (users with is_admin) and their roles. Permissions: staff.{viewAny|view|create|update|delete}. */
class StaffResource extends Resource
{
    use HasResourcePermissions; // shares the User model with Customers: checks staff.{ability}

    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(fn ($q) => $q->where('is_admin', true)->orWhereHas('roles'));
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && $record->getKey() !== auth()->id() && ! RoleGuard::isLastSuperAdmin($record);
    }

    /** "Preview as": every permission this staff member has and the role that grants it (read-only). */
    public static function previewAction(): Action
    {
        return Action::make('previewAs')
            ->label('Preview as')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (User $record) => 'What '.$record->name.' can do')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('4xl')
            ->modalContent(fn (User $record) => PermissionPreview::html($record));
    }

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
