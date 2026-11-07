<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Models\Setting;
use App\Support\Features;
use App\Support\Rbac\RoleGuard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * System → Features: every switch in App\Support\Features::REGISTRY, grouped, with search, per-group on/off,
 * confirmation for risky switches and "last changed by". Needs page.features + features.manage.
 */
class FeatureSwitches extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['features.manage'];

    protected static ?string $slug = 'features';

    protected static ?string $title = 'Features';

    protected static ?string $navigationLabel = 'Features';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.feature-switches';

    /** Switches that change what shoppers can do right now: confirm before flipping. */
    public const RISKY = ['checkout', 'signups', 'pay.*', 'store.*', 'mobile_app'];

    /** @var array<string, bool> */
    public array $states = [];

    public function mount(): void
    {
        Features::flush();
        $this->states = Features::all();
    }

    public static function isRisky(string $key): bool
    {
        foreach (self::RISKY as $p) {
            if ($p === $key || (str_ends_with($p, '*') && str_starts_with($key, rtrim($p, '*')))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, array{label: string, description: string, default: bool}>> */
    public function getGroups(): array
    {
        $groups = [];
        foreach (Features::REGISTRY as $key => [$label, $group, $default, $description]) {
            $groups[$group][$key] = ['label' => $label, 'description' => $description, 'default' => $default];
        }

        return $groups;
    }

    /** @return array<string, string> key => "Changed by X, 5 minutes ago" */
    public function getMeta(): array
    {
        $out = [];
        $rows = Setting::query()->where('key', 'like', 'features._meta.%')->get();
        foreach ($rows as $row) {
            $v = (array) $row->value;
            $at = isset($v['at']) ? Carbon::parse($v['at']) : null;
            $out[substr($row->key, strlen('features._meta.'))] = 'Turned '.(! empty($v['on']) ? 'ON' : 'OFF').' by '.($v['by'] ?? 'unknown')
                .($at ? ', '.$at->diffForHumans() : '');
        }

        return $out;
    }

    public function toggleAction(): Action
    {
        return Action::make('toggle')
            ->label(fn (array $arguments) => ($this->states[$arguments['key'] ?? ''] ?? false) ? 'Turn off' : 'Turn on')
            ->size('sm')
            ->color(fn (array $arguments) => ($this->states[$arguments['key'] ?? ''] ?? false) ? 'gray' : 'primary')
            ->requiresConfirmation(fn (array $arguments) => self::isRisky($arguments['key'] ?? ''))
            ->modalHeading(fn (array $arguments) => (($this->states[$arguments['key'] ?? ''] ?? false) ? 'Turn off ' : 'Turn on ').(Features::REGISTRY[$arguments['key'] ?? ''][0] ?? 'feature').'?')
            ->modalDescription('This changes what shoppers can do immediately, on the website and in the app.')
            ->action(function (array $arguments) {
                $key = $arguments['key'] ?? '';
                if (! isset(Features::REGISTRY[$key])) {
                    return;
                }
                $this->apply([$key], ! ($this->states[$key] ?? false));
            });
    }

    public function groupAction(): Action
    {
        return Action::make('group')
            ->label(fn (array $arguments) => ! empty($arguments['on']) ? 'All on' : 'All off')
            ->size('xs')
            ->link()
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Turn every '.($arguments['section'] ?? '').' switch '.(! empty($arguments['on']) ? 'ON' : 'OFF').'?')
            ->action(function (array $arguments) {
                $keys = array_keys($this->getGroups()[$arguments['section'] ?? ''] ?? []);
                $this->apply($keys, (bool) ($arguments['on'] ?? false));
            });
    }

    /** @param  list<string>  $keys */
    public function apply(array $keys, bool $on): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Filament::auth()->user();
        $changed = [];
        foreach ($keys as $key) {
            if (! isset(Features::REGISTRY[$key]) || ($this->states[$key] ?? null) === $on) {
                continue;
            }
            Features::set($key, $on);
            Setting::put("features._meta.$key", ['on' => $on, 'by' => $user?->name, 'by_id' => $user?->getAuthIdentifier(), 'at' => now()->toIso8601String()]);
            $changed[$key] = [! $on, $on];
        }
        if ($changed) {
            RoleGuard::log('features.changed', null, $changed, 'Feature switches: '.implode(', ', array_map(fn ($k) => $k.'='.($on ? 'on' : 'off'), array_keys($changed))));
        }
        Features::flush();
        $this->states = Features::all();

        Notification::make()
            ->title($changed ? count($changed).' switch'.(count($changed) > 1 ? 'es' : '').' turned '.($on ? 'ON' : 'OFF') : 'Nothing changed')
            ->{$on ? 'success' : 'warning'}()->send();
    }
}
