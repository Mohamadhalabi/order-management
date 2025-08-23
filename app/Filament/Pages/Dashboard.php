<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\ShopStatsOverview;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            RecentOrders::class,      // full width (widget decides)
            ShopStatsOverview::class, // will appear below, in grid columns
        ];
    }

    // grid: 1 col on mobile, 2 on md, 3 on xl (for the widgets that are not 'full')
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
