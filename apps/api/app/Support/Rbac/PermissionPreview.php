<?php

namespace App\Support\Rbac;

use App\Models\User;
use Illuminate\Support\HtmlString;

/** Read-only "Preview as" explanation: each permission a staff member has, and which role grants it. */
class PermissionPreview
{
    /** @return array{roles: list<string>, granted: array<string, array<string, list<string>>>, denied: int, blocked: bool} */
    public static function data(User $user): array
    {
        $resolver = app(PermissionResolver::class);
        $resolver->flush();
        $labels = PermissionRegistry::all();
        $explain = $resolver->explain($user, array_keys($labels));
        $groups = [];
        foreach (PermissionRegistry::resources() as $slug => $r) {
            foreach ($r['abilities'] as $a) {
                $groups['Sections: '.$r['group']]["$slug.$a"] = $explain["$slug.$a"];
            }
        }
        foreach (PermissionRegistry::custom() as $group => $keys) {
            foreach (array_keys($keys) as $k) {
                $groups['Actions: '.$group][$k] = $explain[$k];
            }
        }
        foreach (array_keys(PermissionRegistry::pages()) as $k) {
            $groups['Pages'][$k] = $explain[$k];
        }
        foreach (array_keys(PermissionRegistry::widgets()) as $k) {
            $groups['Dashboard widgets'][$k] = $explain[$k];
        }

        $granted = [];
        $denied = 0;
        foreach ($groups as $g => $keys) {
            foreach ($keys as $k => $roles) {
                if ($roles) {
                    $granted[$g][$k] = $roles;
                } else {
                    $denied++;
                }
            }
        }

        return ['roles' => array_column($resolver->rolesFor($user), 'name'), 'granted' => $granted, 'denied' => $denied, 'blocked' => (bool) $user->blocked_at];
    }

    public static function html(User $user): HtmlString
    {
        $d = self::data($user);
        $labels = PermissionRegistry::all();
        $h = '<div style="font-size:.85rem;line-height:1.5">';
        if ($d['blocked']) {
            $h .= '<p style="color:#e11d48;font-weight:600">Blocked: this account cannot open the admin at all.</p>';
        }
        $h .= '<p>Roles: <strong>'.e(implode(', ', $d['roles']) ?: 'none').'</strong>. '.e((string) $d['denied']).' other permissions are not granted.</p>';
        foreach ($d['granted'] as $group => $keys) {
            $h .= '<h4 style="margin:.9rem 0 .3rem;font-weight:700">'.e($group).'</h4><table style="width:100%;border-collapse:collapse">';
            foreach ($keys as $key => $roles) {
                $h .= '<tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="padding:.2rem .4rem;width:45%"><code>'.e($key).'</code></td>'
                    .'<td style="padding:.2rem .4rem;opacity:.8">'.e($labels[$key] ?? '').'</td>'
                    .'<td style="padding:.2rem .4rem;text-align:right">'.e(implode(', ', $roles)).'</td></tr>';
            }
            $h .= '</table>';
        }

        return new HtmlString($h.'</div>');
    }
}
