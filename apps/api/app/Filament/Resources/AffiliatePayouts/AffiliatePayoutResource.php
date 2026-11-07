<?php

namespace App\Filament\Resources\AffiliatePayouts;

use App\Filament\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Filament\Resources\AffiliatePayouts\Tables\AffiliatePayoutsTable;
use App\Models\AffiliatePayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliatePayoutResource extends Resource
{
    protected static ?string $model = AffiliatePayout::class;

    protected static ?string $slug = 'affiliate-payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Growth';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Payouts';

    public static function table(Table $table): Table
    {
        return AffiliatePayoutsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = AffiliatePayout::query()->where('status', 'requested')->count();

        return $n ? (string) $n : null;
    }

    public static function getPages(): array
    {
        return ['index' => ListAffiliatePayouts::route('/')];
    }
}
