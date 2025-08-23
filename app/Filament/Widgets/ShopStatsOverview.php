<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Carbon;

class ShopStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '60s'; // auto-refresh

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }


    protected function getCards(): array
    {
        $user = auth()->user();

        $ordersQuery = Order::query();
        if ($user?->hasRole('seller') && !$user->hasRole('admin')) {
            $ordersQuery->where('created_by_id', $user->id);
        }

        $today = (clone $ordersQuery)->whereDate('created_at', Carbon::today());
        $month = (clone $ordersQuery)->whereBetween('created_at', [
            now()->startOfMonth(), now()->endOfMonth()
        ]);

        $revenue = (clone $month)->sum('total'); // monthly revenue
        $ordersCount = (clone $month)->count();

        $cards = [
            Card::make('Orders (this month)', number_format($ordersCount))
                ->description('today: ' . (clone $today)->count())
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary'),

            Card::make('Products', number_format(Product::query()->count()))
                ->description('in catalog')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Card::make('Customers', number_format(User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'seller'))
                ->count()))
                ->description('unique customers')
                ->descriptionIcon('heroicon-o-users')
                ->color('success'),
        ];

        // Only admins see revenue
        if ($user?->hasRole('admin')) {
            array_splice($cards, 1, 0, [
                Card::make('Revenue (this month)', 'TRY ' . number_format($revenue, 2))
                    ->description('incl. shipping')
                    ->descriptionIcon('heroicon-o-banknotes')
                    ->color('warning'),
            ]);
        }

        return $cards;
    }
}
