<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use App\Filament\Resources\ProductReviews\Tables\ProductReviewsTable;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductReview;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(3)->columnSpanFull()->schema([
                    TextEntry::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))->color('warning'),
                    TextEntry::make('status')->badge()->color(fn ($state) => ProductReviewsTable::STATUS_COLORS[$state] ?? 'gray'),
                    IconEntry::make('verified_purchase')->boolean(),
                    TextEntry::make('product_id')->label('Product')
                        ->url(fn (ProductReview $r) => ProductResource::getUrl('view', ['record' => $r->product_id])),
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('order_id')->placeholder('—'),
                    TextEntry::make('title')->columnSpanFull()->weight('bold')->placeholder('—'),
                    TextEntry::make('body')->columnSpanFull()->prose(),
                ]),
            ]);
    }
}
