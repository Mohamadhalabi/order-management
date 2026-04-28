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

    public static function canView(): bool
    {
        // Sadece admin ve seller görebilir, depo göremez.
        return auth()->user()?->hasAnyRole(['admin', 'seller']) ?? false;
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    // Shared query logic for the widget
    protected function getFilteredQuery(): Builder
    {
        $query = Order::query()->latest();
        $user = auth()->user();

        if ($user?->hasRole('admin')) {
            return $query;
        }

        if ($user?->hasRole('depo')) {
            return $query->whereIn('status', ['depo', 'hazirlaniyor', 'kargolandi']);
        }

        if ($user?->hasRole('seller')) {
            return $query->where('created_by_id', $user->id);
        }

        return $query;
    }

    protected function baseQuery(): Builder
    {
        return $this->getFilteredQuery();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredQuery())
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
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Müşteri')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->colors([
                        'gray'    => 'taslak',
                        'success' => ['odendi', 'onaylandi', 'tamamlandi'],
                        'warning' => ['depo', 'hazirlaniyor'],
                        'info'    => 'kargolandi',
                        'danger'  => 'iptal',
                    ]),
                Tables\Columns\TextColumn::make('total')->label('Toplam')->money('TRY', true),
                Tables\Columns\TextColumn::make('creator.name')->label('Oluşturan')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->since()->sortable(),
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