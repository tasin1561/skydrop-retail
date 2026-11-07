<?php

namespace App\Http\Middleware;

use App\Support\Knobs;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/** Admin panel: optional IP allowlist (Finance & security settings) and an idle session timeout. */
class AdminGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $allow = array_values(array_filter(array_map('trim', explode(',', (string) Knobs::get('security.admin.ip_allowlist', '')))));
        if ($allow && ! IpUtils::checkIp((string) $request->ip(), $allow)) {
            abort(403, 'The admin panel is not available from this network.');
        }

        $timeout = (int) Knobs::get('security.admin.session_timeout_minutes', 60);
        if ($timeout > 0 && $request->hasSession()) {
            $session = $request->session();
            $last = (int) $session->get('admin_last_seen', 0);
            if ($last && time() - $last > $timeout * 60 && Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $session->invalidate();
                $session->regenerateToken();

                return redirect()->guest(Filament::getLoginUrl() ?? '/admin/login');
            }
            $session->put('admin_last_seen', time());
        }

        return $next($request);
    }
}
