<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProviderHealths\Tables\ProviderHealthsTable;
use App\Models\ProviderHealth;
use Filament\Widgets\TableWidget;
use Filament\Tables\Table;

class ProviderHealthTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Provider health';

    public function table(Table $table): Table
    {
        return $table
            ->query(ProviderHealth::query()->orderByRaw("case state when 'open' then 0 when 'half_open' then 1 else 2 end")->orderBy('provider'))
            ->columns(ProviderHealthsTable::columns())
            ->paginated(false)
            ->poll('30s')
            ->emptyStateHeading('No provider calls yet')
            ->emptyStateDescription('Providers appear here after their first call.');
    }
}
