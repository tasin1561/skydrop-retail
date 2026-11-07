<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\ProductReviews\Tables\ProductReviewsTable;
use App\Models\ProductReview;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'productReviews';

    protected static ?string $title = 'Reviews';

    public function getRelationship(): Relation|Builder
    {
        return $this->getOwnerRecord()->hasMany(ProductReview::class, 'user_id');
    }

    /** The relation is built in getRelationship() because User (commerce module) does not define it. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return ProductReviewsTable::configure($table, nested: true);
    }
}
