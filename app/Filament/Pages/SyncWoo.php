<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Jobs\SyncWooProducts;
use App\Jobs\SyncWooUsers;
use App\Services\WooSyncService; // 👈 add

class SyncWoo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $title = 'Woo Sync';
    protected static string $view = 'filament.pages.sync-woo';


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole('admin') ?? false;
    }

    public static function canAccess(): bool
    {
        // Same check used when visiting the page URL directly
        return static::shouldRegisterNavigation();
    }

    // Original: queue jobs
    public function syncProducts(): void
    {
        SyncWooProducts::dispatch();

        Notification::make()
            ->title('Product sync started')
            ->success()
            ->send();
    }

    public function syncUsers(): void
    {
        SyncWooUsers::dispatch();

        Notification::make()
            ->title('User sync started')
            ->success()
            ->send();
    }

    // Debug: run immediately (no queue)
    public function debugProducts(): void
    {
        try {
            $count = app(WooSyncService::class)->syncProducts();
            Notification::make()
                ->title("Product sync completed: {$count} items")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            logger()->error('Debug product sync failed', ['msg' => $e->getMessage()]);
            Notification::make()
                ->title('Product sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function debugUsers(): void
    {
        try {
            $count = app(WooSyncService::class)->syncUsers();
            Notification::make()
                ->title("User sync completed: {$count} items")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            logger()->error('Debug user sync failed', ['msg' => $e->getMessage()]);
            Notification::make()
                ->title('User sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
