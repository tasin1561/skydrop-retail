<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('users:id,name')->withCount('users'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Role $r) => $r->description),
                TextColumn::make('slug')->badge()->color('gray'),
                TextColumn::make('permissions')->label('Permissions')
                    ->state(fn (Role $r) => in_array('*', $r->permissions ?? [], true) ? 'All (*)'.(count($r->permissions) > 1 ? ' minus '.(count($r->permissions) - 1).' rule(s)' : '') : count($r->permissions ?? []).' keys'),
                TextColumn::make('users.name')->label('Staff')->badge()->limitList(4)->expandableLimitedList()->placeholder('—'),
                IconColumn::make('is_system')->label('Built-in')->boolean(),
            ])
            ->recordActions([EditAction::make(), RoleResource::cloneAction(), DeleteAction::make()]);
    }
}
