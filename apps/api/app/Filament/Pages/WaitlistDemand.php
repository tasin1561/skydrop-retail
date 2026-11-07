<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Admin;
use App\Models\PreorderRule;
use App\Models\Product;
use App\Models\ProductWatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Restock waitlist demand: which out-of-stock products have the most customers waiting (back-in-stock alerts). */
class WaitlistDemand extends Page implements HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Waitlist demand';

    public static function query(): Builder
    {
        $waiting = ProductWatch::query()->selectRaw('count(*)')->whereColumn('product_watches.product_id', 'products.id')
            ->where('type', 'back_in_stock')->where('active', true);

        return Product::query()->select('products.*')->selectSub($waiting, 'waiting')
            ->whereIn('id', ProductWatch::query()->select('product_id')->where('type', 'back_in_stock')->where('active', true));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::query())
            ->description('Customers waiting for a restock alert. Flag popular items as pre-orderable to take deposits now.')
            ->defaultSort('waiting', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->imageSize(40)->extraImgAttributes(['style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('title')->limit(70)->wrap()->searchable()->description(fn (Product $p) => Admin::marketLabel($p->market).' · '.$p->id)
                    ->url(fn (Product $p) => ProductResource::getUrl('view', ['record' => $p->id])),
                TextColumn::make('waiting')->label('Waiting')->sortable()->badge()->color('danger'),
                TextColumn::make('in_stock')->label('Stock')->badge()->formatStateUsing(fn ($state) => $state ? 'In stock' : 'Out of stock')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('preorder')->label('Pre-order')->state(fn (Product $p) => PreorderRule::query()->where('active', true)
                    ->where(fn ($q) => $q->where(fn ($w) => $w->where('scope', 'product')->where('target', $p->id))
                        ->orWhere(fn ($w) => $w->where('scope', 'category')->where('target', $p->category)))->exists() ? 'Yes' : '—'),
                TextColumn::make('price_checked_at')->label('Checked')->since(),
            ])
            ->filters([TernaryFilter::make('in_stock')->label('In stock')->default(false)])
            ->recordActions([
                Action::make('allowPreorder')->label('Allow pre-order')->icon(Heroicon::OutlinedCalendarDays)->visible(fn () => Admin::can('preorders.allow'))
                    ->schema([
                        TextInput::make('deposit_pct')->numeric()->default(30)->minValue(5)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('eta_days')->numeric()->default(21)->minValue(1)->suffix('days')->required(),
                    ])
                    ->action(function (Product $record, array $data) {
                        PreorderRule::query()->updateOrCreate(['scope' => 'product', 'target' => $record->id], $data + ['active' => true]);
                        Notification::make()->title('Pre-orders open for this product')->success()->send();
                    }),
            ]);
    }
}
