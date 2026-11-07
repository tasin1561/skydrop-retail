<?php

namespace App\Filament\Resources\ProductRequests;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ProductRequests\Pages\ListProductRequests;
use App\Filament\Resources\ProductRequests\Pages\ViewProductRequest;
use App\Filament\Support\Admin;
use App\Models\ProductRequest;
use App\Services\Shopping\ProductRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Inbox for "Request a product": new → sourcing → quoted → ordered / declined, with a "Send quote" action. */
class ProductRequestResource extends Resource
{
    protected static ?string $model = ProductRequest::class;

    protected static ?string $slug = 'product-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Product requests';

    public const COLORS = ['new' => 'danger', 'sourcing' => 'warning', 'quoted' => 'info', 'ordered' => 'success', 'declined' => 'gray'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('request')->label('Request')->wrap()->state(fn (ProductRequest $r) => mb_strimwidth((string) ($r->description ?: $r->url), 0, 90, '…'))
                    ->description(fn (ProductRequest $r) => $r->url && $r->description ? mb_strimwidth($r->url, 0, 60, '…') : null)
                    ->searchable(['description', 'url']),
                TextColumn::make('phone')->label('Customer')->state(fn (ProductRequest $r) => $r->name ?: ($r->user?->name ?? '—'))->description(fn (ProductRequest $r) => $r->phone)
                    ->searchable(['phone', 'name']),
                TextColumn::make('qty'),
                TextColumn::make('budget_bdt')->label('Budget')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ProductRequest::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('quote_bdt')->label('Quote')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—')
                    ->url(fn (ProductRequest $r) => $r->quote_order_id ? OrderResource::getUrl('view', ['record' => $r->quote_order_id]) : null),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->recordActions([ViewAction::make(), self::sendQuote(), self::changeStatus()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => ProductRequest::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextEntry::make('name')->placeholder('—')->helperText(fn (ProductRequest $r) => $r->phone),
                TextEntry::make('qty'),
                TextEntry::make('budget_bdt')->label('Budget')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—'),
                TextEntry::make('url')->columnSpanFull()->url(fn (ProductRequest $r) => $r->url)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('product_id')->label('Matched product')->placeholder('—'),
                TextEntry::make('search_query')->label('Searched for')->placeholder('—'),
                TextEntry::make('source'),
                TextEntry::make('created_at')->dateTime(),
                ImageEntry::make('photo_url')->label('Photo')->columnSpanFull()->imageHeight(160)->placeholder('No photo'),
                TextEntry::make('quote_bdt')->label('Quote')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—'),
                TextEntry::make('quote_order_id')->label('Quote order')->placeholder('—')
                    ->url(fn (ProductRequest $r) => $r->quote_order_id ? OrderResource::getUrl('view', ['record' => $r->quote_order_id]) : null),
                TextEntry::make('quote_note')->placeholder('—')->columnSpan(2),
                TextEntry::make('admin_note')->label('Staff note')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function sendQuote(): Action
    {
        return Action::make('sendQuote')->label('Send quote')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
            ->visible(fn (ProductRequest $record) => in_array($record->status, ['new', 'sourcing'], true) && Admin::can('requests.quote'))
            ->schema(fn (ProductRequest $record) => [
                TextInput::make('title')->label('Item name on the order')->maxLength(200)->default(mb_strimwidth((string) ($record->description ?: $record->url), 0, 120, '')),
                TextInput::make('price')->label('Price per item (BDT, delivered)')->numeric()->minValue(1)->required()->prefix('৳'),
                TextInput::make('shipping')->label('Shipping (BDT)')->numeric()->minValue(0)->default(0)->prefix('৳'),
                Textarea::make('note')->label('Message to the customer')->rows(2)->maxLength(300),
            ])
            ->modalDescription('Creates an order with this custom line (payment pending) and texts the customer a pay link.')
            ->action(function (ProductRequest $record, array $data) {
                $order = app(ProductRequestService::class)->quote($record, (int) $data['price'], $data['note'] ?? null, $data['title'] ?? null, (int) ($data['shipping'] ?? 0));
                Notification::make()->title('Quote sent')->body('Order '.$order->id.' created; the customer got a pay link.')->success()->send();
            });
    }

    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')->label('Status')->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->visible(fn (ProductRequest $record) => (ProductRequestService::FLOW[$record->status] ?? []) !== [] && Admin::can('requests.status'))
            ->schema(fn (ProductRequest $record) => [
                ToggleButtons::make('status')->inline()->required()->colors(self::COLORS)
                    ->options(array_intersect_key(ProductRequest::STATUSES, array_flip(ProductRequestService::FLOW[$record->status] ?? []))),
                Textarea::make('admin_note')->label('Staff note')->rows(2)->default($record->admin_note),
            ])
            ->action(fn (ProductRequest $record, array $data) => app(ProductRequestService::class)->setStatus($record, $data['status'], $data['admin_note'] ?? null));
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ProductRequest::query()->where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListProductRequests::route('/'), 'view' => ViewProductRequest::route('/{record}')];
    }
}
