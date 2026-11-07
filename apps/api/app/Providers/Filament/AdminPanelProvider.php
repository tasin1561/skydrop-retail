<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Middleware\AdminGuard;
use App\Http\Middleware\AdminRequire2fa;
use App\Http\Middleware\AuthorizePanelPage;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate; // filters widgets by permission (RBAC)
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Security module: TOTP 2FA with recovery codes (required for admin/manager), profile page to manage it.
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Skydrop Retail Admin')],
                isRequired: true, // routes always registered; AdminRequire2fa decides per user (admin/manager) at runtime
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(AdminRequire2fa::class)
            ->brandName('Skydrop Retail Admin')
            ->brandLogo(fn () => new HtmlString(
                '<span style="display:flex;align-items:center;gap:.5rem;font-weight:700;font-size:1.05rem">'
                .'<img src="'.asset('images/skydrop-retail-logo.svg').'" alt="" style="height:2rem;width:2rem">'
                .'<span>Skydrop Retail <span style="color:#ff4d2e">Admin</span></span></span>'
            ))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/skydrop-retail-logo.svg'))
            ->colors([
                // Midnight Luxe violet. The generated 600 shade was too light for white text, so Filament fell back to pale
                // lavender buttons that looked disabled; pin 500-700 to the brand violet (5.6:1 on white).
                'primary' => array_replace(Color::hex('#5b3df5'), [500 => '#7357f7', 600 => '#5b3df5', 700 => '#4a2fd6']),
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'gray' => Color::Zinc,
            ])
            ->font('Plus Jakarta Sans', provider: GoogleFontProvider::class)
            ->darkMode()
            ->databaseNotifications() // fulfilment: staff alerts (price alerts, returns, unboxing problems)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearch()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                // Every group is listed so the sidebar order is fixed (unlisted groups would float above Sales).
                NavigationGroup::make('Sales'),
                NavigationGroup::make('Hub'), // fulfilment: Kolkata hub receiving, packing, return-to-seller
                NavigationGroup::make('Catalog'),
                NavigationGroup::make('Pricing'),
                NavigationGroup::make('Growth'),
                NavigationGroup::make('Wallet & Plus'),
                NavigationGroup::make('Messaging'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('System'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                AdminGuard::class, // IP allowlist + idle timeout (security module)
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/invoices', InvoiceController::class)->name('invoices');
            })
            ->authMiddleware([
                Authenticate::class,
                AuthorizePanelPage::class, // RBAC: page.{slug} backstop
            ]);
    }
}
