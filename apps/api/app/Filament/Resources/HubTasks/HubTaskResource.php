<?php

namespace App\Filament\Resources\HubTasks;

use App\Filament\Resources\HubTasks\Pages\ListHubTasks;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Fulfilment\HubReceipt;
use App\Models\Fulfilment\HubTask;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Hub work queue: items to return to the seller after a damaged / wrong-item discrepancy. */
class HubTaskResource extends Resource
{
    protected static ?string $model = HubTask::class;

    protected static ?string $slug = 'hub-tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Hub';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Return to seller';

    protected static ?string $modelLabel = 'hub task';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = HubTask::query()->where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('item.image')->label('')->imageSize(44)->extraImgAttributes(['style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('item.title')->label('Item')->limit(60)->wrap()->description(fn (HubTask $r) => $r->item ? 'IT-'.$r->item->id.' · '.$r->item->market.' · ref '.($r->item->purchase_ref ?: '—') : null),
                TextColumn::make('order_id')->label('Order')->fontFamily('mono')->searchable()
                    ->url(fn (HubTask $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => HubTask::TYPES[$state] ?? $state),
                TextColumn::make('note')->wrap()->limit(80),
                TextColumn::make('photos')->label('Hub photos')
                    ->state(fn (HubTask $r) => count((array) HubReceipt::query()->find($r->hub_receipt_id)?->photos))
                    ->formatStateUsing(fn ($state) => $state ? $state.' photo(s)' : '—')
                    ->url(fn (HubTask $r) => HubReceipt::query()->find($r->hub_receipt_id)?->photoUrls()[0] ?? null, true),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'open' ? 'danger' : 'success'),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(['open' => 'Open', 'done' => 'Done'])->default('open')])
            ->recordActions([
                Action::make('done')->label('Mark done')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (HubTask $r) => $r->status === 'open')
                    ->schema([Textarea::make('note')->label('Seller return reference / note')->rows(2)])
                    ->action(function (HubTask $record, array $data) {
                        $record->forceFill(['status' => 'done', 'done_at' => now(),
                            'note' => trim($record->note."\n".($data['note'] ?? ''))])->save();
                        Notification::make()->title('Task done')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListHubTasks::route('/')];
    }
}
