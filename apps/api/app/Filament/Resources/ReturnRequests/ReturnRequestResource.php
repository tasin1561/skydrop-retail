<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Pages\ViewReturnRequest;
use App\Filament\Support\Admin;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Fulfilment\Upload;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static ?string $slug = 'returns';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Returns';

    protected static ?string $modelLabel = 'return';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ReturnRequest::query()->where('status', 'requested')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            // Skydrop Plus priority support first, then newest.
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('priority')->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('priority')->label('Priority')->badge()->color('warning')->icon(Heroicon::Star)->sortable()
                    ->getStateUsing(fn ($record) => $record->priority ? 'Plus priority' : null)
                    ->tooltip('Skydrop Plus member with priority support'),
                TextColumn::make('order_id')->label('Order')->fontFamily('mono')->searchable()
                    ->url(fn (ReturnRequest $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextColumn::make('user.name')->label('Customer')->description(fn (ReturnRequest $r) => $r->user?->phone)->searchable(),
                TextColumn::make('stage')->badge()->formatStateUsing(fn ($state) => $state === 'india' ? 'At Kolkata hub' : 'After delivery')
                    ->color(fn ($state) => $state === 'india' ? 'warning' : 'gray'),
                TextColumn::make('reason')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                TextColumn::make('resolution')->label('Wants')->formatStateUsing(fn ($state) => ReturnRequest::RESOLUTIONS[$state] ?? $state),
                TextColumn::make('refund_bdt')->label('Refund')->formatStateUsing(fn ($state) => Admin::bdt($state))->alignEnd()
                    ->description(fn (ReturnRequest $r) => (float) $r->return_fee_bdt > 0 ? 'after ৳'.number_format((float) $r->return_fee_bdt).' return shipping' : null),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ReturnActions::statusColor($state)),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReturnRequest::STATUSES),
                SelectFilter::make('stage')->options(['india' => 'At Kolkata hub', 'delivered' => 'After delivery']),
                TernaryFilter::make('priority')->label('Priority (Skydrop Plus)'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Case')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)->color(fn ($state) => ReturnActions::statusColor($state)),
                TextEntry::make('reason')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                TextEntry::make('resolution')->label('Customer wants')->formatStateUsing(fn ($state) => ReturnRequest::RESOLUTIONS[$state] ?? $state),
                TextEntry::make('order_id')->label('Order')->url(fn (ReturnRequest $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextEntry::make('refund_bdt')->label('Value')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                TextEntry::make('replacement_order_id')->label('Replacement')->placeholder('—')
                    ->url(fn (ReturnRequest $r) => $r->replacement_order_id ? OrderResource::getUrl('view', ['record' => $r->replacement_order_id]) : null),
                TextEntry::make('items_list')->label('Items')->columnSpanFull()->listWithLineBreaks()
                    ->state(fn (ReturnRequest $r) => collect((array) $r->items)->map(fn ($l) => $l['qty'].' × '.(OrderItem::query()->find($l['orderItemId'])?->title ?? 'item '.$l['orderItemId']))->all()),
                TextEntry::make('note')->label('Customer note')->columnSpanFull()->placeholder('—'),
                TextEntry::make('photos_html')->label('Photos')->columnSpanFull()
                    ->state(fn (ReturnRequest $r) => new HtmlString(Upload::query()->whereIn('id', (array) $r->photos)->get()
                        ->map(fn (Upload $u) => '<a href="'.e($u->signedUrl()).'" target="_blank" rel="noopener"><img src="'.e($u->signedUrl()).'" alt="Return photo" style="height:120px;border-radius:.5rem;display:inline-block;margin:0 .5rem .5rem 0"></a>')
                        ->implode('') ?: '—')),
                TextEntry::make('staff_notes')->label('Staff notes')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Timeline')->columnSpan(1)->schema([
                TextEntry::make('timeline_list')->hiddenLabel()->listWithLineBreaks()
                    ->state(fn (ReturnRequest $r) => collect((array) $r->timeline)->map(fn ($e) => Carbon::parse($e['at'])->timezone('Asia/Dhaka')->format('d M H:i')
                        .' · '.(ReturnRequest::STATUSES[$e['status']] ?? $e['status']).($e['note'] ? ' — '.$e['note'] : ''))->all()),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'view' => ViewReturnRequest::route('/{record}'),
        ];
    }
}
