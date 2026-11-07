<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryAdmin;
use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Product;
use App\Services\Categories\CategoryWriter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        $unmatched = Product::query()->whereNull('category_id')->count();

        return [
            Action::make('tree')->label('Tree view')->icon(Heroicon::OutlinedBars3BottomLeft)->color('gray')
                ->url(CategoryResource::getUrl('tree')),
            Action::make('unmatched')->label("Unmatched products ($unmatched)")->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->color($unmatched ? 'warning' : 'gray')->url(CategoryResource::getUrl('unmatched')),
            ActionGroup::make([
                Action::make('recount')->label('Recount products')->icon(Heroicon::OutlinedCalculator)->action(fn () => CategoryAdmin::recount()),
                Action::make('match')->label('Match uncategorised products')->icon(Heroicon::OutlinedSparkles)
                    ->action(function () {
                        [$m, $u] = app(CategoryWriter::class)->categorize();
                        app(CategoryWriter::class)->recount();
                        Notification::make()->title("Matched $m products, $u still unmatched")->success()->send();
                    }),
            ])->label('Tools')->button()->color('gray'),
            CreateAction::make(),
        ];
    }
}
