<?php

namespace App\Filament\Resources\RestrictedRules\Tables;

use App\Filament\Support\Admin;
use App\Models\RestrictedRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RestrictedRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('action')->badge()->color(fn ($state) => ['block' => 'danger', 'warn' => 'warning', 'surcharge' => 'info'][$state] ?? 'gray')
                    ->description(fn (RestrictedRule $r) => $r->action === 'surcharge' ? '+'.Admin::bdt($r->surcharge_bdt).'/unit' : null),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('pattern')->fontFamily('mono')->size('xs')->limit(60)->tooltip(fn (RestrictedRule $r) => $r->pattern)->searchable(),
                TextColumn::make('message')->limit(80)->wrap()->searchable(),
                ToggleColumn::make('active'),
            ])
            ->filters([SelectFilter::make('action')->options(RestrictedRule::ACTIONS), SelectFilter::make('type')->options(RestrictedRule::TYPES)])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())])]);
    }
}
