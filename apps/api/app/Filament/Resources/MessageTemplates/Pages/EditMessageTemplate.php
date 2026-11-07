<?php

namespace App\Filament\Resources\MessageTemplates\Pages;

use App\Filament\Resources\MessageTemplates\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Services\Messaging\Messenger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testSend')->label('Send test')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->modalDescription('Sends the saved version of this template, rendered with sample data, right now.')
                ->schema(fn () => [
                    TextInput::make('to')->required()
                        ->label(fn () => match ($this->record->channel) {
                            'sms' => 'Phone', 'email' => 'Email', default => 'User id or phone (needs the app installed)'
                        })
                        ->default(fn () => match ($this->record->channel) {
                            'sms' => Filament::auth()->user()?->phone, 'email' => Filament::auth()->user()?->email, default => Filament::auth()->user()?->id,
                        }),
                ])
                ->action(function (array $data) {
                    /** @var MessageTemplate $t */
                    $t = $this->record;
                    $log = app(Messenger::class)->test($t, (string) $data['to']);
                    $log?->status === 'sent'
                        ? Notification::make()->title('Test sent')->body('Via '.$log->provider)->success()->send()
                        : Notification::make()->title('Test failed')->body($log?->error ?? 'No recipient found')->danger()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
