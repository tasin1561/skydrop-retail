<?php

namespace App\Filament\Resources\ProductMatches;

use App\Filament\Resources\ProductMatches\Pages\ListProductMatches;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Admin;
use App\Models\ProductMatch;
use App\Services\PricingService;
use App\Services\Shopping\ProductMatcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Compare stores: review the matcher's pairs (confirm shows them to shoppers; reject hides them for good). */
class ProductMatchResource extends Resource
{
    protected static ?string $model = ProductMatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Store matches';

    public const STATUSES = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected'];

    public const COLORS = ['pending' => 'warning', 'confirmed' => 'success', 'rejected' => 'gray'];

    public static function table(Table $table): Table
    {
        $label = fn (?\App\Models\Product $p) => $p ? '['.$p->market.'] '.mb_strimwidth($p->title, 0, 60, '…') : '(removed)';
        $price = fn (?\App\Models\Product $p) => $p ? Admin::bdt(app(PricingService::class)->productBdt($p)).' · '.$p->id : null;

        return $table
            ->modifyQueryUsing(fn ($q) => $q->with(['product', 'match']))
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('product_id')->label('Listing A')->searchable()->wrap()
                    ->formatStateUsing(fn ($state, ProductMatch $r) => $label($r->product))->description(fn (ProductMatch $r) => $price($r->product))
                    ->url(fn (ProductMatch $r) => ProductResource::getUrl('view', ['record' => $r->product_id])),
                TextColumn::make('match_id')->label('Listing B')->searchable()->wrap()
                    ->formatStateUsing(fn ($state, ProductMatch $r) => $label($r->match))->description(fn (ProductMatch $r) => $price($r->match))
                    ->url(fn (ProductMatch $r) => ProductResource::getUrl('view', ['record' => $r->match_id])),
                TextColumn::make('method')->badge()->formatStateUsing(fn ($state) => ['gtin' => 'GTIN/EAN', 'model' => 'Model no.', 'title' => 'Title'][$state] ?? $state),
                TextColumn::make('score')->numeric(2)->sortable()
                    ->description(fn (ProductMatch $r) => $r->status === 'pending' ? ($r->score >= ProductMatcher::AUTO_SHOW ? 'shown' : 'hidden until confirmed') : null),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->default('pending'),
                SelectFilter::make('method')->options(['gtin' => 'GTIN/EAN', 'model' => 'Model no.', 'title' => 'Title']),
            ])
            ->recordActions([
                Action::make('confirm')->icon(Heroicon::OutlinedCheck)->color('success')->visible(fn (ProductMatch $r) => $r->status !== 'confirmed')
                    ->action(fn (ProductMatch $r) => $r->update(['status' => 'confirmed', 'reviewed_by' => auth()->id()])),
                Action::make('reject')->icon(Heroicon::OutlinedXMark)->color('danger')->visible(fn (ProductMatch $r) => $r->status !== 'rejected')
                    ->action(fn (ProductMatch $r) => $r->update(['status' => 'rejected', 'reviewed_by' => auth()->id()])),
            ])
            ->toolbarActions([
                BulkAction::make('confirmAll')->label('Confirm selected')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'confirmed', 'reviewed_by' => auth()->id()])),
                BulkAction::make('rejectAll')->label('Reject selected')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'rejected', 'reviewed_by' => auth()->id()])),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ProductMatch::query()->where('status', 'pending')->where('score', '<', ProductMatcher::AUTO_SHOW)->count();

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
        return ['index' => ListProductMatches::route('/')];
    }
}
