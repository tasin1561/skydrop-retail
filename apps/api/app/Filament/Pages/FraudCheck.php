<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Services\Audit\ActivityLogger;
use App\Services\Commerce\Phone;
use App\Services\Fraud\FraudService;
use App\Services\Security\Blocklist;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Look up any phone: courier delivery history (Steadfast, Pathao, CarryBee (RedX pending)) plus our own signals. */
class FraudCheck extends Page
{
    use HasPagePermission; // RBAC: page.{slug} + the keys below

    /** @var list<string> */
    protected static array $requiredPermissions = ['fraud.override'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Fraud check';

    protected string $view = 'filament.ops.fraud-check';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $phone = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('phone')->label('Customer phone')->placeholder('01XXXXXXXXX')->required()->maxLength(25),
        ]);
    }

    public function check(): void
    {
        $phone = Phone::normalize((string) ($this->form->getState()['phone'] ?? ''));
        if (! $phone) {
            Notification::make()->title('Enter a valid Bangladeshi mobile number.')->danger()->send();

            return;
        }
        $this->phone = $phone;
        $this->result = app(FraudService::class)->score($phone);
        ActivityLogger::log('fraud.lookup', null, [], 'Fraud check for '.Phone::mask($phone), ['score' => $this->result['score']]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')->label('Block this phone')->color('danger')->icon(Heroicon::OutlinedNoSymbol)
                ->visible(fn () => $this->phone !== null && ! Blocklist::isBlocked('phone', $this->phone))
                ->requiresConfirmation()
                ->schema([TextInput::make('reason')->required()->maxLength(200)])
                ->action(function (array $data) {
                    Blocklist::block('phone', (string) $this->phone, $data['reason'], 'fraud');
                    Notification::make()->title('Phone blocked')->success()->send();
                }),
        ];
    }
}
