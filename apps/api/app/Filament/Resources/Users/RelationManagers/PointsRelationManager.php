<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\PointsLedgers\Tables\PointsLedgersTable;
use App\Models\PointsLedger;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

class PointsRelationManager extends RelationManager
{
    protected static string $relationship = 'pointsLedger';

    protected static ?string $title = 'Points history';

    /** User has no pointsLedger() relation of its own (commerce module owns User), so build it here. */
    public function getRelationship(): Relation|Builder
    {
        $owner = $this->getOwnerRecord();

        return method_exists($owner, 'pointsLedger')
            ? $owner->pointsLedger()
            : $owner->hasMany(PointsLedger::class, 'user_id');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Schema::hasTable('points_ledger');
    }

    public function table(Table $table): Table
    {
        return PointsLedgersTable::configure($table, forCustomer: true);
    }
}
