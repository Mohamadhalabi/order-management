<?php

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;

class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('seller')
            ->path('seller')
            ->login()
            ->authGuard('web')

            // Branding
            ->brandLogo(asset('images/Logo-Normal.webp'))
            ->brandLogoHeight('2.5rem')
            ->brandName('')

            // Theme assets (no Vite)
            ->assets([
                Css::make('brand', asset('filament/brand.css')),
            ])

            // Use your brand color everywhere (also override "warning" to kill orange)
            ->colors([
                'primary' => Color::hex('#2D83B0'),
                'warning' => Color::hex('#2D83B0'),
                'gray'    => Color::Gray,
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
                'role:seller',
            ]);
    }
}
