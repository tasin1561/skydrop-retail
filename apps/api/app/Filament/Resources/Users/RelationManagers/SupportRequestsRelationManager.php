<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\SupportRequest;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class SupportRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'supportRequests';

    protected static ?string $title = 'Support requests';

    public function getRelationship(): Relation|Builder
    {
        return $this->getOwnerRecord()->hasMany(SupportRequest::class, 'user_id');
    }

    /** The relation is built in getRelationship() because User (commerce module) does not define it. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
    }
}
