<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ShopStatsOverview extends BaseWidget
{
    // ✅ non-static, matches the parent
    protected ?string $heading = 'Özet';

    // you may keep this static (it is static in the parent)
    protected static ?string $pollingInterval = '60s';

    // optional: make the cards span full width
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        $orders = Order::query();

        // Sellers see only their own orders
        if ($user?->hasRole('seller') && ! $user->hasRole('admin')) {
            $orders->where('created_by_id', $user->id);
        }

        $startOfMonth     = Carbon::now()->startOfMonth();
        $ordersThisMonth  = (clone $orders)->where('created_at', '>=', $startOfMonth)->count();
        $revenueThisMonth = (clone $orders)->where('created_at', '>=', $startOfMonth)->sum('total');
        $uniqueCustomers  = (clone $orders)->distinct('customer_id')->count('customer_id');
        $productsCount    = Product::query()->count();

        return [
            Stat::make('Bu ay sipariş', number_format($ordersThisMonth))
                ->description('Toplam sipariş adedi')
                ->icon('heroicon-o-shopping-bag'),

            Stat::make('Ciro (bu ay)', 'TRY ' . number_format($revenueThisMonth, 2))
                ->description('Kargo dahil')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Ürün', number_format($productsCount))
                ->description('Katalogda')
                ->icon('heroicon-o-cube'),

            Stat::make('Müşteri', number_format($uniqueCustomers))
                ->description('Benzersiz müşteri')
                ->icon('heroicon-o-user-group'),
        ];
    }
}
