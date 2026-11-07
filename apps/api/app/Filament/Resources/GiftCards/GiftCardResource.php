<?php

namespace App\Filament\Resources\GiftCards;

use App\Filament\Resources\GiftCards\Pages\ListGiftCards;
use App\Filament\Support\Admin;
use App\Filament\Support\MoneyAdmin;
use App\Models\GiftCard;
use App\Services\Money\GiftCardService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

class GiftCardResource extends Resource
{
    protected static ?string $model = GiftCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGiftTop;

    protected static string|UnitEnum|null $navigationGroup = MoneyAdmin::GROUP;

    protected static ?int $navigationSort = 2;

    public const COLORS = ['pending' => 'gray', 'active' => 'success', 'redeemed' => 'info', 'void' => 'danger', 'expired' => 'warning'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->formatStateUsing(fn ($state) => GiftCard::format($state))->fontFamily('mono')->copyable()
                    ->copyableState(fn (GiftCard $r) => $r->formatted())->searchable(),
                TextColumn::make('initial_bdt')->label('Value')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable(),
                TextColumn::make('balance_bdt')->label('Balance')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => GiftCard::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('purchaser.name')->label('Bought by')->placeholder('—')->searchable(),
                TextColumn::make('recipient_name')->label('Recipient')->description(fn (GiftCard $r) => $r->recipient_phone ?? $r->recipient_email)->placeholder('—'),
                TextColumn::make('redeemer.name')->label('Redeemed by')->description(fn (GiftCard $r) => $r->redeemed_at?->format('d M Y H:i'))->placeholder('—'),
                TextColumn::make('batch')->placeholder('—')->toggleable(isToggledHiddenByDefault: true)->searchable(),
                TextColumn::make('expires_at')->date()->placeholder('Never')->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(GiftCard::STATUSES)->multiple(),
                SelectFilter::make('source')->options(['purchase' => 'Purchased', 'admin' => 'Issued by staff', 'promo' => 'Promo (bulk)']),
            ])
            ->recordActions([
                Action::make('usage')->label('Usage')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (GiftCard $r) => new HtmlString(self::usageHtml($r))),
                Action::make('void')->label('Void')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (GiftCard $r) => MoneyAdmin::can('giftcards.void') && in_array($r->status, ['active', 'pending', 'expired'], true))
                    ->requiresConfirmation()->schema([TextInput::make('reason')->required()->maxLength(200)])
                    ->action(fn (GiftCard $r, array $data) => MoneyAdmin::run(fn () => app(GiftCardService::class)->void($r, $data['reason']), 'Gift card voided')),
            ])
            ->headerActions([self::issueAction(), self::bulkAction()]);
    }

    public static function issueAction(): Action
    {
        return Action::make('issue')->label('Issue gift card')->icon(Heroicon::OutlinedPlus)
            ->visible(fn () => MoneyAdmin::can('giftcards.issue'))
            ->schema([
                TextInput::make('amount')->prefix('৳')->numeric()->minValue(1)->required(),
                Select::make('design')->options(config('money.gift_cards.designs'))->default('classic'),
                TextInput::make('recipient_name')->maxLength(100),
                TextInput::make('recipient_phone')->tel()->maxLength(20),
                TextInput::make('recipient_email')->email()->maxLength(190),
                Textarea::make('message')->rows(2)->maxLength(300),
                DateTimePicker::make('expires_at')->default(now()->addYear()),
                Toggle::make('deliver')->label('Send the code to the recipient now (SMS / email)')->default(true),
            ])
            ->action(function (array $data) {
                MoneyAdmin::run(function () use ($data) {
                    $card = app(GiftCardService::class)->issue((float) $data['amount'], $data, 'admin');
                    Notification::make()->title('Code: '.$card->formatted())->persistent()->info()->send();
                }, 'Gift card issued');
            });
    }

    public static function bulkAction(): Action
    {
        return Action::make('bulk')->label('Bulk promo cards')->icon(Heroicon::OutlinedSquare3Stack3d)->color('gray')
            ->visible(fn () => MoneyAdmin::can('giftcards.bulk'))
            ->modalDescription('Generates active promo cards and downloads their codes as CSV.')
            ->schema([
                TextInput::make('count')->numeric()->minValue(1)->maxValue(5000)->required()->default(50),
                TextInput::make('amount')->prefix('৳')->numeric()->minValue(1)->required()->default(200),
                TextInput::make('batch')->label('Batch name')->maxLength(40)->placeholder('e.g. EID-2026'),
                DateTimePicker::make('expires_at')->default(now()->addMonths(3)),
            ])
            ->action(function (array $data) {
                $cards = app(GiftCardService::class)->bulk((int) $data['count'], (float) $data['amount'], $data['expires_at'] ?? null, $data['batch'] ?: null);

                return response()->streamDownload(function () use ($cards) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['code', 'value_bdt', 'batch', 'expires_at']);
                    foreach ($cards as $c) {
                        fputcsv($out, [$c->formatted(), (int) $c->initial_bdt, $c->batch, $c->expires_at?->toDateString()]);
                    }
                    fclose($out);
                }, 'gift-cards-'.($cards[0]->batch ?? 'batch').'.csv', ['Content-Type' => 'text/csv']);
            });
    }

    private static function usageHtml(GiftCard $r): string
    {
        $rows = [
            'Code' => $r->formatted(), 'Value' => Admin::bdt($r->initial_bdt), 'Balance' => Admin::bdt($r->balance_bdt),
            'Status' => GiftCard::STATUSES[$r->status] ?? $r->status, 'Order' => $r->order_id ?? '—',
            'Bought by' => $r->purchaser ? $r->purchaser->name.' ('.$r->purchaser->phone.')' : '—',
            'Recipient' => trim(($r->recipient_name ?? '').' '.($r->recipient_phone ?? '').' '.($r->recipient_email ?? '')) ?: '—',
            'Message' => $r->message ?? '—', 'Delivered' => $r->delivered_at?->format('d M Y H:i') ?? '—',
            'Redeemed by' => $r->redeemer ? $r->redeemer->name.' ('.$r->redeemer->phone.') on '.$r->redeemed_at?->format('d M Y H:i') : '—',
            'Expires' => $r->expires_at?->format('d M Y') ?? 'Never', 'Void reason' => $r->void_reason ?? '—',
        ];
        $html = '<dl style="display:grid;grid-template-columns:auto 1fr;gap:.4rem 1rem;font-size:.875rem">';
        foreach ($rows as $k => $v) {
            $html .= '<dt style="opacity:.6">'.e($k).'</dt><dd>'.e((string) $v).'</dd>';
        }

        return $html.'</dl>';
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
        return ['index' => ListGiftCards::route('/')];
    }
}
