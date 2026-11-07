<?php

namespace App\Filament\Resources\ApiLogs\Tables;

use App\Filament\Support\Admin;
use App\Models\ApiLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M, H:i:s')->sortable(),
                TextColumn::make('provider')->badge()->sortable(),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('market')->placeholder('—'),
                TextColumn::make('key')->limit(40)->searchable()->tooltip(fn (ApiLog $r) => $r->key),
                TextColumn::make('status')->badge()->sortable()
                    ->color(fn ($state) => $state >= 200 && $state < 300 ? 'success' : ($state === 0 || $state >= 500 ? 'danger' : 'warning')),
                TextColumn::make('ms')->label('Time')->suffix(' ms')->numeric()->sortable()->alignEnd()
                    ->color(fn ($state) => $state > 15000 ? 'danger' : ($state > 8000 ? 'warning' : null))
                    ->summarize(Average::make()->label('Avg')->numeric(0)),
                IconColumn::make('rendered')->label('JS')->boolean()->toggleable(),
                TextColumn::make('cost_usd')->label('Cost')->money('USD', 4)->sortable()->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('USD', 2)),
                TextColumn::make('error')->limit(40)->color('danger')->placeholder('—')->toggleable()->tooltip(fn (ApiLog $r) => $r->error),
            ])
            ->filters([
                SelectFilter::make('provider')->options(fn () => ApiLog::query()->distinct()->pluck('provider', 'provider')->all())->multiple(),
                SelectFilter::make('type')->options(fn () => ApiLog::query()->distinct()->pluck('type', 'type')->all()),
                SelectFilter::make('market')->options(Admin::MARKETS),
                SelectFilter::make('outcome')->label('Status')
                    ->options(['ok' => 'Success (2xx)', 'client' => 'Client error (4xx)', 'error' => 'Failed (5xx / timeout)'])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'ok' => $q->whereBetween('status', [200, 299]),
                        'client' => $q->whereBetween('status', [400, 499]),
                        'error' => $q->where(fn ($q) => $q->where('status', '>=', 500)->orWhere('status', 0)),
                        default => $q,
                    }),
                Filter::make('created_at')->label('Date')
                    ->schema([DatePicker::make('from')->default(now()->startOfMonth()), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
