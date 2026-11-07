<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Filament\Support\Admin;
use App\Models\Affiliate;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Growth: affiliates ranked by commission earned on orders placed this month (Dhaka time), reversed excluded. */
class TopAffiliates extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top affiliates this month';

    public function table(Table $table): Table
    {
        $from = now('Asia/Dhaka')->startOfMonth()->utc();
        $month = fn (Builder $q) => $q->where('created_at', '>=', $from)->where('status', '!=', 'reversed');

        return $table
            ->query(Affiliate::query()->with('user')
                ->withCount(['commissions as month_orders' => $month, 'clicks as month_clicks' => fn (Builder $q) => $q->where('created_at', '>=', $from)])
                ->withSum(['commissions as month_bdt' => $month], 'amount_bdt')
                ->whereHas('commissions', $month))
            ->defaultSort('month_bdt', 'desc')
            ->columns([
                TextColumn::make('code')->weight('bold')->url(fn (Affiliate $r) => AffiliateResource::getUrl('edit', ['record' => $r])),
                TextColumn::make('user.name')->label('Name'),
                TextColumn::make('month_clicks')->label('Clicks'),
                TextColumn::make('month_orders')->label('Orders'),
                TextColumn::make('month_bdt')->label('Commission')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable(),
            ])
            ->paginated([5, 10])
            ->emptyStateHeading('No affiliate sales yet this month');
    }
}
