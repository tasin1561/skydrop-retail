<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Filament\Support\Admin;
use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(20)->unique(ignoreRecord: true),
                    TextInput::make('password')->password()->revealable()->minLength(10)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                    Hidden::make('is_admin')->default(true),
                ]),
                Section::make('Roles')
                    ->description('A staff member gets every permission of every role ticked here. Edit what each role can do under System → Roles.')
                    ->schema([
                        CheckboxList::make('role_ids')->hiddenLabel()->columns(2)->required()->bulkToggleable()
                            ->options(fn () => Role::query()->orderByDesc('is_system')->orderBy('id')->pluck('name', 'id')->all())
                            ->descriptions(fn () => Role::query()->pluck('description', 'id')->map(fn ($d) => (string) $d)->all())
                            ->afterStateHydrated(function (CheckboxList $component, ?User $record) {
                                if ($record) {
                                    $component->state($record->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->all()
                                        ?: self::legacyRoleIds($record));
                                }
                            })
                            ->dehydrated(false)
                            ->disabled(fn () => ! Admin::can('staff.manage')),
                        Toggle::make('blocked')->label('Blocked (cannot sign in to the admin)')
                            ->afterStateHydrated(fn (Toggle $component, ?User $record) => $component->state((bool) $record?->blocked_at))
                            ->dehydrated(false),
                    ]),
            ]);
    }

    /** Pre-tick the role a legacy users.role string maps to. */
    private static function legacyRoleIds(User $user): array
    {
        $slug = \App\Support\Rbac\DefaultRoles::LEGACY[$user->role ?: 'admin'] ?? null;

        return $slug ? Role::query()->where('slug', $slug)->pluck('id')->map(fn ($id) => (string) $id)->all() : [];
    }
}
