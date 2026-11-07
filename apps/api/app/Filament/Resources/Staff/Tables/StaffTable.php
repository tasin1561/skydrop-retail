<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Support\Rbac\PermissionResolver;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->with('roles'))
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('roles_list')->label('Roles')->badge()
                    ->state(fn (User $r) => array_column(app(PermissionResolver::class)->rolesFor($r), 'name'))
                    ->color(fn (string $state) => str_contains($state, 'Super Admin') ? 'danger' : (str_contains($state, 'legacy') ? 'gray' : 'info')),
                TextColumn::make('blocked_at')->label('Blocked')->since()->placeholder('—')->color('danger'),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([StaffResource::previewAction(), EditAction::make(), DeleteAction::make()]);
    }
}
