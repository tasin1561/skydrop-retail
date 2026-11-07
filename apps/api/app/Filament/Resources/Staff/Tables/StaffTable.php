<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Filament\Support\Admin;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('role')->badge()->default('admin')
                    ->formatStateUsing(fn ($state) => Admin::ROLES[$state ?: 'admin'] ?? $state)
                    ->color(fn ($state) => ['admin' => 'danger', 'manager' => 'warning', 'support' => 'info'][$state ?: 'admin'] ?? 'gray'),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
