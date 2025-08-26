<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Pages;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')

            // Branding
            ->brandLogo(asset('images/Logo-Normal.webp'))
            ->brandLogoHeight('2.5rem')
            ->brandName('') // hide default text

            // Load CSS overrides from /public
            ->assets([
                Css::make('brand', asset('filament/brand.css')),
            ])

            // Brand color palette
            ->colors([
                'primary' => Color::hex('#2D83B0'),
                'warning' => Color::hex('#2D83B0'),
                'gray'    => Color::Gray,
            ])

            // Discover
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->pages([ Pages\Dashboard::class ])

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
            ]);
    }
}
