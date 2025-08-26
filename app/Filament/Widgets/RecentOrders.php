<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Son Siparişler';

    public function query(): Builder
    {
        $q = Order::query()->latest();

        $user = auth()->user();
        if ($user?->hasRole('seller') && !$user->hasRole('admin')) {
            $q->where('created_by_id', $user->id);
        }

        return $q;
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Müşteri')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'info'    => 'shipped',
                        'danger'  => 'cancelled',
                        'gray'    => 'draft',
                    ]),
                Tables\Columns\TextColumn::make('total')->label('Toplam')->money('try', true),
                Tables\Columns\TextColumn::make('creator.name')->label('Oluşturan')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->button() // renders a full button (not the tiny chip)
                    ->extraAttributes(['style' => 'background-color:#2D83B0;color:#fff'])
                    ->url(fn (Order $r) => route('orders.pdf', $r), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Düzenle')
                    ->visible(fn () => auth()->user()?->hasRole('admin')) // (optional) hide from sellers
                    ->url(fn (Order $r) => route('filament.admin.resources.orders.edit', ['record' => $r])),
            ]);
    }
}
