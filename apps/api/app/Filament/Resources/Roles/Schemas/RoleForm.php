<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Role;
use App\Support\Rbac\PermissionRegistry;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $locked = fn (?Role $record) => (bool) $record?->isSuperAdmin();

        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state, '_')) : null),
                TextInput::make('slug')->required()->maxLength(60)->alphaDash()->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record) => (bool) $record?->is_system)->dehydrated(),
                Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull(),
                TextEntry::make('staff')->label('Staff with this role')->columnSpanFull()
                    ->state(fn (?Role $record) => $record ? ($record->users()->orderBy('name')->pluck('name')->implode(', ') ?: 'Nobody yet') : 'Nobody yet'),
            ]),
            Section::make('Permissions')
                ->description(fn (?Role $record) => $locked($record)
                    ? 'Super Admin always has every permission (*), including sections other modules add later.'
                    : 'Tick what this role may do. Sections other modules add later appear here automatically.')
                ->schema([
                    ViewField::make('permissions_exact')->hiddenLabel()->view('filament.rbac.permission-matrix')
                        ->viewData(['matrix' => self::matrix()])->default([])->disabled($locked)->dehydrated(),
                    TagsInput::make('permissions_rules')->label('Wildcard & deny rules (advanced)')
                        ->placeholder('e.g. orders.*  page.hub*  !staff.*')
                        ->helperText('"*" = everything, "orders.*" = every orders key, "!key" removes a key this role would otherwise get. Wildcards also cover sections added later. Restriction keys (".scope.") must be ticked explicitly.')
                        ->disabled($locked)->dehydrated(),
                ]),
        ]);
    }

    /** Split stored permissions into matrix (exact keys) and rules (wildcards / denials). */
    public static function split(array $permissions): array
    {
        $rules = array_values(array_filter($permissions, fn ($p) => str_contains($p, '*') || str_starts_with($p, '!')));

        return ['permissions_exact' => array_values(array_diff($permissions, $rules)), 'permissions_rules' => $rules];
    }

    public static function merge(array $data): array
    {
        $data['permissions'] = array_values(array_unique(array_filter(array_map('trim', [...($data['permissions_exact'] ?? []), ...($data['permissions_rules'] ?? [])]))));
        unset($data['permissions_exact'], $data['permissions_rules']);

        return $data;
    }

    /** @return array{abilities: list<string>, areas: array<string, list<array>>, lists: array<string, array<string, string>>} */
    public static function matrix(): array
    {
        $resources = PermissionRegistry::resources();
        $custom = PermissionRegistry::custom();
        $used = [];
        $areas = [];
        $abilities = [...PermissionRegistry::ABILITIES, 'export'];
        foreach ($resources as $slug => $r) {
            $extra = [];
            foreach ($custom as $keys) {
                foreach ($keys as $k => $label) {
                    if (str_starts_with($k, $slug.'.')) {
                        $extra[$k] = $label;
                        $used[$k] = true;
                    }
                }
            }
            $areas[$r['group']][] = [
                'slug' => $slug,
                'label' => $r['label'],
                'abilities' => collect($abilities)->mapWithKeys(fn ($a) => [$a => in_array($a, $r['abilities'], true) ? "$slug.$a" : null])->all(),
                'extra' => $extra,
                'search' => $slug.' '.$r['label'].' '.$r['group'].' '.implode(' ', $extra).' '.implode(' ', array_keys($extra)),
            ];
        }
        ksort($areas);

        $lists = [];
        foreach ($custom as $group => $keys) {
            $left = array_diff_key($keys, $used);
            if ($left) {
                $lists['Actions: '.$group] = $left;
            }
        }
        // Pages that also need action keys (HasPagePermission::$requiredPermissions) say so; duplicate labels get their group.
        $pages = PermissionRegistry::pages();
        $counts = array_count_values(array_column($pages, 'label'));
        $lists['Pages'] = array_map(function ($p) use ($counts) {
            $label = $p['label'].($counts[$p['label']] > 1 && $p['group'] ? ' ('.$p['group'].')' : '');
            $needs = method_exists($p['class'], 'requiredPermissions') ? $p['class']::requiredPermissions() : [];

            return $needs ? $label.' — also needs '.implode(', ', $needs) : $label;
        }, $pages);
        $lists['Dashboard widgets'] = array_map(fn ($w) => $w['label'], PermissionRegistry::widgets());

        return ['abilities' => $abilities, 'areas' => $areas, 'lists' => $lists];
    }
}
