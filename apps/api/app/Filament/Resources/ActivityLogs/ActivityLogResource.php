<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Support\Admin;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Finance\CsvExport;
use App\Support\AdminSecurity;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only staff activity log: model changes with diffs, admin actions, sign-ins, exports and security events. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $slug = 'activity-log';

    public static function canViewAny(): bool
    {
        return AdminSecurity::can('activity.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->dateTime('d M Y, H:i:s', 'Asia/Dhaka'),
            TextEntry::make('user_name')->label('Staff')->placeholder('system / anonymous'),
            TextEntry::make('action')->badge(),
            TextEntry::make('subject')->state(fn (ActivityLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
            TextEntry::make('description')->columnSpanFull()->placeholder('—'),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            TextEntry::make('user_agent')->label('User agent')->placeholder('—'),
            TextEntry::make('changes')->columnSpanFull()->state(fn (ActivityLog $r) => Admin::jsonHtml($r->changes ?? []))->html(),
            TextEntry::make('meta')->columnSpanFull()->state(fn (ActivityLog $r) => Admin::jsonHtml($r->meta ?? []))->html(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M, H:i:s', 'Asia/Dhaka')->sortable(),
                TextColumn::make('user_name')->label('Staff')->placeholder('system')->searchable(),
                TextColumn::make('action')->badge()->color(fn (string $state) => match (true) {
                    str_starts_with($state, 'security.'), $state === 'login_failed', $state === 'deleted' => 'danger',
                    str_starts_with($state, 'action:') => 'info', $state === 'export' => 'warning', default => 'gray',
                })->searchable(),
                TextColumn::make('subject_type')->label('Model')->formatStateUsing(fn ($state) => class_basename((string) $state))->placeholder('—'),
                TextColumn::make('subject_id')->label('Record')->searchable()->placeholder('—'),
                TextColumn::make('description')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Staff')
                    ->options(fn () => User::query()->where('is_admin', true)->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('subject_type')->label('Model')
                    ->options(fn () => ActivityLog::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')
                        ->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
                SelectFilter::make('action')->options(fn () => ActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                Filter::make('created_at')->label('Date')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('export')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => AdminSecurity::can('activity-log.export') || Admin::isAdmin())
                    ->action(fn (Collection $records) => CsvExport::download('activity-log',
                        ['id', 'at', 'staff', 'action', 'model', 'record', 'description', 'ip'],
                        $records->map(fn ($r) => [$r->id, $r->created_at?->toDateTimeString(), $r->user_name, $r->action, $r->subject_type, $r->subject_id, $r->description, $r->ip])->all())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
