<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->profile(isSimple: false)
            // 2FA cu aplicatie de autentificare (TOTP) si coduri de recuperare pentru personal.
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->brandName('Pet Market')
            ->brandLogo(asset('media/brand/pet-market-logo.png'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                // Paleta HiKeVet (muralul fatadei): cobalt, galben, magenta, mandarina, verde.
                'primary' => Color::hex('#1e3ba8'),
                'success' => Color::hex('#13958a'),
                'warning' => Color::hex('#ffc21f'),
                'danger' => Color::hex('#e6177a'),
                'info' => Color::hex('#ff6a1a'),
                'gray' => Color::Zinc,
            ])
            ->font('Jost Variable', url: asset('fonts/jost.css'), provider: LocalFontProvider::class)
            ->globalSearch(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            // Ca in WooCommerce, dar grupat pe ce faci zilnic: vanzari, catalog, clienti, marketing, continut, sistem.
            ->navigationGroups([
                NavigationGroup::make('Vânzări')->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make('Catalog')->icon('heroicon-o-cube'),
                NavigationGroup::make('Clienți')->icon('heroicon-o-users'),
                NavigationGroup::make('Marketing')->icon('heroicon-o-megaphone')->collapsed(),
                NavigationGroup::make('Conținut')->icon('heroicon-o-document-text')->collapsed(),
                NavigationGroup::make('Sistem')->icon('heroicon-o-cog-6-tooth')->collapsed(),
            ])
            ->databaseNotifications(false)
            ->spa()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->viteTheme('resources/css/admin.css')
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.login-buddy'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
