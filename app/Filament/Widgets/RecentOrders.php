<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Son Siparişler';

    public function getColumnSpan(): int|string|array
    {
        // Full width inside the content area
        return 'full';
    }

    protected function baseQuery(): Builder
    {
        return Order::query()
            ->latest()
            ->when(
                auth()->user()?->hasRole('seller') && ! auth()->user()?->hasRole('admin'),
                fn (Builder $q) => $q->where('created_by_id', auth()->id())
            );
    }

    public function table(Table $table): Table
    {
        $base = fn (): Builder => Order::query()
            ->latest()
            ->when(
                auth()->user()?->hasRole('seller') && ! auth()->user()?->hasRole('admin'),
                fn (Builder $q) => $q->where('created_by_id', auth()->id())
            );

        return $table
            ->query($base())

            // “Tabs” via pill filters
            ->filters([
                Tables\Filters\Filter::make('tamamlandi')
                    ->label('Tamamlandı')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereIn('status', ['odendi', 'onaylandi'])
                    ),

                Tables\Filters\Filter::make('kargolandi')
                    ->label('Kargolandı')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('status', 'kargolandi')
                    ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)

            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Müşteri')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->colors([
                        'gray'    => 'taslak',
                        'success' => ['odendi', 'onaylandi'],
                        'info'    => 'kargolandi',
                        'danger'  => 'iptal',
                    ]),

                Tables\Columns\TextColumn::make('total')
                    ->label('Toplam')
                    ->money('TRY', true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oluşturan')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturma')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->button()
                    ->extraAttributes(['style' => 'background-color:#2D83B0;color:#fff'])
                    ->url(fn (Order $r) => route('orders.pdf', $r), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Düzenle')
                    ->visible(fn () => auth()->user()?->hasRole('admin'))
                    ->url(fn (Order $r) => route('filament.admin.resources.orders.edit', ['record' => $r])),
            ]);
    }
}
