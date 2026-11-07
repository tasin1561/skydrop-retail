<?php

namespace App\Filament\Resources\WalletLedgers;

use App\Filament\Resources\WalletLedgers\Pages\ListWalletLedgers;
use App\Filament\Support\Admin;
use App\Filament\Support\MoneyAdmin;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\Money\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Skydrop Wallet ledger (read-only) + staff credit/debit. */
class WalletLedgerResource extends Resource
{
    protected static ?string $slug = 'wallet-ledgers';

    protected static ?string $model = WalletLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = MoneyAdmin::GROUP;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Wallet ledger';

    protected static ?string $modelLabel = 'wallet entry';

    protected static ?string $pluralModelLabel = 'wallet ledger';

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('user.name')->label('Customer')->description(fn (WalletLedger $r) => $r->user?->phone)->searchable(['name', 'phone']),
                TextColumn::make('reason')->badge()->formatStateUsing(fn ($state) => WalletLedger::REASONS[$state] ?? $state),
                TextColumn::make('amount_bdt')->label('Amount')->alignEnd()->weight('bold')
                    ->formatStateUsing(fn ($state, WalletLedger $r) => ($r->type === 'credit' ? '+' : '−').Admin::bdt($state))
                    ->color(fn (WalletLedger $r) => $r->type === 'credit' ? 'success' : 'danger'),
                TextColumn::make('balance_after_bdt')->label('Balance after')->formatStateUsing(fn ($state) => Admin::bdt($state))->alignEnd(),
                TextColumn::make('order_id')->label('Order')->placeholder('—')->searchable(),
                TextColumn::make('note')->limit(50)->placeholder('—'),
                TextColumn::make('expires_at')->date()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['credit' => 'Credit', 'debit' => 'Debit']),
                SelectFilter::make('reason')->options(WalletLedger::REASONS)->multiple(),
            ])
            ->headerActions([
                Action::make('adjust')->label('Adjust wallet')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn () => MoneyAdmin::can('wallet.adjust'))
                    ->schema([
                        MoneyAdmin::customerSelect(),
                        Select::make('type')->options(['credit' => 'Credit (add)', 'debit' => 'Debit (remove)'])->required()->default('credit'),
                        Select::make('reason')->options(['admin' => 'Admin adjustment', 'promo' => 'Promotion', 'refund' => 'Refund'])->default('admin')->required(),
                        TextInput::make('amount')->prefix('৳')->numeric()->minValue(1)->required(),
                        TextInput::make('note')->required()->maxLength(200),
                        DateTimePicker::make('expires_at')->label('Credit expires (optional)'),
                    ])
                    ->action(function (array $data) {
                        $user = User::query()->findOrFail($data['user_id']);
                        $wallet = app(WalletService::class);
                        MoneyAdmin::run(fn () => $data['type'] === 'credit'
                            ? $wallet->credit($user, (float) $data['amount'], $data['reason'], $data['note'], null,
                                ! empty($data['expires_at']) ? \Illuminate\Support\Carbon::parse($data['expires_at']) : null, [], auth()->id())
                            : $wallet->debit($user, (float) $data['amount'], 'admin', $data['note'], null, [], auth()->id()), 'Wallet updated');
                    }),
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
        return ['index' => ListWalletLedgers::route('/')];
    }
}
