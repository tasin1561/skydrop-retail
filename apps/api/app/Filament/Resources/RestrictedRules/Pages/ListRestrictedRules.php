<?php

namespace App\Filament\Resources\RestrictedRules\Pages;

use App\Filament\Resources\RestrictedRules\RestrictedRuleResource;
use App\Services\Cms\RestrictionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRestrictedRules extends ListRecords
{
    protected static string $resource = RestrictedRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label('Test a title')->icon(Heroicon::OutlinedBeaker)->color('gray')
                ->modalDescription('Check what a product title would trigger, using the active rules.')
                ->schema([
                    TextInput::make('title')->required()->maxLength(500)->placeholder('Xiaomi 20000mAh Power Bank'),
                    TextInput::make('category')->placeholder('electronics'),
                    TextInput::make('brand'),
                ])
                ->modalSubmitActionLabel('Test')
                ->action(fn (array $data) => self::notifyTest($data)),
            CreateAction::make(),
        ];
    }

    public static function notifyTest(array $data): array
    {
        $hit = app(RestrictionService::class)->test((string) $data['title'], (string) ($data['category'] ?? ''), (string) ($data['brand'] ?? ''));
        $n = Notification::make()->persistent();
        if (! $hit) {
            $n->title('No rule matches. This item can be ordered.')->success();
        } else {
            $n->title(strtoupper($hit['action']).' (rule #'.$hit['ruleId'].')')->body($hit['message'].($hit['surchargeBdt'] ? ' Surcharge ৳'.$hit['surchargeBdt'].'.' : ''))
                ->{$hit['action'] === 'block' ? 'danger' : 'warning'}();
        }
        $n->send();

        return $hit ?? [];
    }
}
