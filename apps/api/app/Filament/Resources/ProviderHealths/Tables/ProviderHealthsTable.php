<?php

namespace App\Filament\Resources\ProviderHealths\Tables;

use App\Filament\Support\Admin;
use App\Models\ApiLog;
use App\Models\ProviderHealth;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProviderHealthsTable
{
    public const STATE_LABELS = ['closed' => 'Healthy', 'half_open' => 'Recovering', 'open' => 'Down (circuit open)'];

    public const STATE_COLORS = ['closed' => 'success', 'half_open' => 'warning', 'open' => 'danger'];

    public static function configure(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->columns(self::columns())
            ->paginated(false)
            ->recordActions([
                Action::make('reset')->label('Reset circuit')->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (ProviderHealth $r) => Admin::canManage() && $r->state !== 'closed')
                    ->requiresConfirmation()
                    ->modalDescription('Marks the provider healthy so the next request tries it again.')
                    ->action(function (ProviderHealth $record) {
                        $record->forceFill(['state' => 'closed', 'fail_count' => 0, 'opened_at' => null])->save();
                        Notification::make()->title($record->provider.' reset')->success()->send();
                    }),
            ]);
    }

    /** @return array<int, TextColumn> */
    public static function columns(): array
    {
        return [
            TextColumn::make('provider')->weight('bold'),
            TextColumn::make('state')->badge()
                ->formatStateUsing(fn ($state) => self::STATE_LABELS[$state] ?? $state)
                ->color(fn ($state) => self::STATE_COLORS[$state] ?? 'gray'),
            TextColumn::make('fail_count')->label('Fails')->numeric()->color(fn ($state) => $state > 0 ? 'danger' : null),
            TextColumn::make('last_success_at')->label('Last success')->since()->placeholder('never'),
            TextColumn::make('last_failure_at')->label('Last failure')->since()->placeholder('never'),
            TextColumn::make('success_24h')->label('Success 24 h')
                ->state(function (ProviderHealth $r) {
                    $q = ApiLog::query()->where('provider', $r->provider)->where('created_at', '>=', now()->subDay());
                    $total = (clone $q)->count();

                    return $total ? round((clone $q)->whereBetween('status', [200, 299])->count() / $total * 100).'% of '.$total : '—';
                }),
            TextColumn::make('avg_ms')->label('Avg time 24 h')
                ->state(fn (ProviderHealth $r) => ($ms = ApiLog::query()->where('provider', $r->provider)->where('created_at', '>=', now()->subDay())->avg('ms')) ? number_format($ms).' ms' : '—'),
        ];
    }
}
