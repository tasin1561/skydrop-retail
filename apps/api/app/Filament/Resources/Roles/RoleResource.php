<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Filament\Support\Admin;
use App\Models\Role;
use App\Support\Rbac\RoleGuard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/** Staff roles and their permission matrix. Permissions: roles.{ability}; editing also needs roles.manage. */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Roles';

    protected static bool $isGloballySearchable = false;

    public static function canCreate(): bool
    {
        return parent::canCreate() && Admin::can('roles.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && Admin::can('roles.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && Admin::can('roles.manage') && ! $record->isSuperAdmin();
    }

    public static function cloneAction(): Action
    {
        return Action::make('clone')
            ->label('Clone role')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn () => static::canCreate())
            ->schema([TextInput::make('name')->required()->maxLength(80)->default(fn (Role $record) => $record->name.' (copy)')])
            ->action(function (Role $record, array $data) {
                $base = Str::slug($data['name'], '_') ?: 'role';
                $slug = $base;
                for ($i = 2; Role::query()->where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'_'.$i;
                }
                $copy = Role::query()->create(['name' => $data['name'], 'slug' => $slug, 'description' => $record->description,
                    'is_system' => false, 'permissions' => $record->permissions ?? []]);
                RoleGuard::log('roles.cloned', $copy, ['from' => $record->slug], "Cloned role {$record->name} as {$copy->name}");
                Notification::make()->title('Role cloned')->success()->send();

                return redirect(static::getUrl('edit', ['record' => $copy]));
            });
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
