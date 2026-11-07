<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Services\Assistant\AssistantSettings as Settings;
use App\Support\Features;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** AI assistant settings (settings table "assistant.*"); on/off is the central feature switch "ai_assistant". */
class AssistantSettings extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['settings.manage'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'AI assistant';

    protected static ?string $title = 'AI assistant settings';

    protected static ?string $slug = 'assistant-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        $state = ['enabled' => Settings::enabled()];
        foreach (Settings::KEYS as $key) {
            $v = Settings::get($key);
            if (in_array($key, ['prompts.en', 'prompts.bn'], true)) {
                $v = implode("\n", Settings::prompts(substr($key, -2)));
            } elseif ($key === 'holidays') {
                $v = collect(Settings::holidays())->map(fn ($h) => trim($h['date'].' | '.$h['name'].(isset($h['note']) ? ' | '.$h['note'] : '')))->implode("\n");
            }
            $state[self::field($key)] = $v;
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $models = (array) config('assistant.models');
        $num = fn (string $key, string $label) => TextInput::make(self::field($key))->label($label)->numeric()->minValue(0)->required();

        return $schema->statePath('data')->components([
            Section::make('Assistant')->columns(3)->schema([
                Toggle::make('enabled')->label('Enabled')->helperText('Same as the "AI shopping assistant" feature switch. Off hides the launcher everywhere.')->columnSpanFull(),
                Select::make(self::field('model'))->label('Model')->options($models)->required(),
                Select::make(self::field('fallback_model'))->label('Cheaper fallback model')->options($models)->required()
                    ->helperText('Used when the main model is overloaded, and after the threshold below.'),
                $num('fallback_at_pct', 'Switch to fallback at')->suffix('% of budget')->maxValue(100)->helperText('0 = never'),
                TextInput::make('api_key_status')->label('ANTHROPIC_API_KEY')->disabled()->dehydrated(false)
                    ->formatStateUsing(fn () => Settings::hasKey() ? 'Set in the server environment' : 'Missing: the assistant runs in basic search mode'),
            ]),
            Section::make('Budget & limits')->description('At the monthly budget the assistant stops calling Claude and answers with plain search results.')->columns(4)->schema([
                $num('monthly_budget_usd', 'Monthly budget')->prefix('$'),
                $num('limits.guest', 'Guest messages / day')->helperText('Per IP'),
                $num('limits.user', 'Signed-in messages / day'),
                $num('limits.plus', 'Plus member messages / day'),
            ]),
            Section::make('Greeting & suggested prompts')->columns(2)->schema([
                Textarea::make(self::field('greeting.en'))->label('Greeting (English)')->rows(3)->maxLength(400),
                Textarea::make(self::field('greeting.bn'))->label('Greeting (বাংলা)')->rows(3)->maxLength(400),
                Textarea::make(self::field('prompts.en'))->label('Suggested prompts (English)')->rows(5)->helperText('One per line, up to 6'),
                Textarea::make(self::field('prompts.bn'))->label('Suggested prompts (বাংলা)')->rows(5)->helperText('One per line, up to 6'),
            ]),
            Section::make('Holiday cutoffs')->description('Used by the delivery estimate. Order-by date = holiday minus 12 days.')->schema([
                Textarea::make(self::field('holidays'))->hiddenLabel()->rows(4)->placeholder("2027-03-10 | Eid-ul-Fitr | Date depends on the moon\n2027-05-17 | Eid-ul-Adha"),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save settings')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        if (class_exists(Features::class)) {
            Features::set('ai_assistant', (bool) ($state['enabled'] ?? false));
        }
        foreach (Settings::KEYS as $key) {
            $v = $state[self::field($key)] ?? null;
            if (in_array($key, ['prompts.en', 'prompts.bn'], true)) {
                $v = array_slice(array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $v) ?: []))), 0, 6);
            } elseif (is_numeric($v) && ! str_starts_with($key, 'greeting')) {
                $v = $v + 0;
            }
            Settings::put($key, $v);
        }
        Notification::make()->title('AI assistant settings saved')->success()->send();
    }
}
