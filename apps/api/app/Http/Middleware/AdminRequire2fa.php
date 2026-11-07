<?php

namespace App\Http\Middleware;

use App\Support\AdminSecurity;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/** Admin and manager roles must have TOTP set up (Filament app authentication); other staff may opt in. */
class AdminRequire2fa extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! AdminSecurity::requires2fa()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
