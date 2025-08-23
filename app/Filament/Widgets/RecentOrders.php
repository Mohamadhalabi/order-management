<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';

    /** REQUIRED in your Filament version */
    public function query(): Builder
    {
        $query = Order::query()->latest();

        $user = auth()->user();
        if ($user?->hasRole('seller') && !$user->hasRole('admin')) {
            $query->where('created_by_id', $user->id);
        }

        return $query;
    }
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())           // <- call the method above
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'info'    => 'shipped',
                        'danger'  => 'cancelled',
                        'gray'    => 'draft',
                    ]),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('try', true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->tooltip('Open PDF')
                    ->visible(fn (Order $r) => filled($r->pdf_path))
                    ->url(fn (Order $r) => $r->pdf_url, shouldOpenInNewTab: true),

                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->url(fn (Order $r) => route('filament.admin.resources.orders.edit', ['record' => $r])),
            ]);
    }
}
