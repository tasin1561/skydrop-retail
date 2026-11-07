<?php

namespace App\Http\Middleware;

use App\Services\Security\ClientIdentity;
use App\Support\AdminSecurity;
use App\Support\Limits;
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
        $allow = AdminSecurity::allowlist();
        if ($allow && ! IpUtils::checkIp(ClientIdentity::ip($request), $allow)) { // real client IP behind Cloudflare (TRUST_CF_CONNECTING_IP)
            abort(403, 'The admin panel is not available from this network.');
        }

        $timeout = Limits::int('api.admin_session_minutes'); // System → Limits (same knob as Finance & security)
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
