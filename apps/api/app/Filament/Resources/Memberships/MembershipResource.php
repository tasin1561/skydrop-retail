<?php

namespace App\Filament\Resources\Memberships;

use App\Filament\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Support\MoneyAdmin;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Money\MembershipService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Skydrop Plus members: grant / extend / cancel. Renewal is manual (reminder + pay link), see MembershipService. */
class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = MoneyAdmin::GROUP;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Plus members';

    protected static ?string $modelLabel = 'Plus membership';

    public static function table(Table $table): Table
    {
        return $table->defaultSort('renews_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Customer')->description(fn (Membership $r) => $r->user?->phone)->searchable(['name', 'phone']),
                TextColumn::make('plan.name')->label('Plan')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state, Membership $r) => $r->isCurrent() ? ($state === 'cancelled' ? 'Cancelled (until end)' : 'Active') : 'Expired')
                    ->color(fn ($state, Membership $r) => $r->isCurrent() ? ($state === 'cancelled' ? 'warning' : 'success') : 'gray'),
                TextColumn::make('started_at')->date()->sortable(),
                TextColumn::make('renews_at')->label('Paid through')->date()->sortable()
                    ->color(fn (Membership $r) => $r->renews_at && $r->renews_at->isBefore(now()->addDays(3)) ? 'danger' : null),
                TextColumn::make('payment_refs')->label('Payments')->state(fn (Membership $r) => count($r->payment_refs ?? [])),
                TextColumn::make('last_credit_at')->label('Last wallet credit')->date()->placeholder('—')->toggleable(),
                TextColumn::make('note')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Membership::STATUSES),
                SelectFilter::make('plan_id')->label('Plan')->relationship('plan', 'name'),
            ])
            ->headerActions([
                Action::make('grant')->label('Grant Plus')->icon(Heroicon::OutlinedPlus)
                    ->visible(fn () => MoneyAdmin::can('membership.grant'))
                    ->schema([
                        MoneyAdmin::customerSelect(),
                        Select::make('plan_id')->label('Plan')->required()->options(fn () => MembershipPlan::query()->orderBy('sort')->pluck('name', 'id')),
                        TextInput::make('periods')->label('Periods')->numeric()->minValue(1)->maxValue(24)->default(1)->required(),
                        TextInput::make('note')->required()->maxLength(200)->placeholder('e.g. Influencer / compensation'),
                    ])
                    ->action(fn (array $data) => MoneyAdmin::run(fn () => app(MembershipService::class)->extendOrStart(
                        User::query()->findOrFail($data['user_id']), MembershipPlan::query()->findOrFail($data['plan_id']), (int) $data['periods'],
                        ['source' => 'admin', 'staff' => auth()->id(), 'note' => $data['note']], $data['note']), 'Plus granted')),
            ])
            ->recordActions([
                Action::make('extend')->label('Extend')->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn () => MoneyAdmin::can('membership.extend'))
                    ->schema([
                        TextInput::make('periods')->label('Plan periods')->numeric()->minValue(1)->maxValue(24)->default(1)->required(),
                        TextInput::make('note')->required()->maxLength(200),
                    ])
                    ->action(fn (Membership $record, array $data) => MoneyAdmin::run(fn () => app(MembershipService::class)->extendOrStart(
                        $record->user, $record->plan, (int) $data['periods'], ['source' => 'admin', 'staff' => auth()->id(), 'note' => $data['note']], $data['note']), 'Membership extended')),
                Action::make('cancel')->label('Cancel')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Membership $record) => MoneyAdmin::can('membership.cancel') && $record->isCurrent())
                    ->requiresConfirmation()
                    ->schema([
                        Toggle::make('immediately')->label('End benefits now (otherwise they run until the paid-through date)'),
                        TextInput::make('note')->required()->maxLength(200),
                    ])
                    ->action(fn (Membership $record, array $data) => MoneyAdmin::run(fn () => app(MembershipService::class)->cancel($record, (bool) ($data['immediately'] ?? false), $data['note']), 'Membership cancelled')),
            ]);
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

    public static function getPages(): array
    {
        return ['index' => ListMemberships::route('/')];
    }
}
