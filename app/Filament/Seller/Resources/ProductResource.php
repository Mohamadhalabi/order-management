<?php

namespace App\Filament\Seller\Resources;

use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Seller\Resources\ProductResource\Pages;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $slug = 'products';
    protected static ?string $navigationLabel = 'Products';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')->searchable()->limit(60),

                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->size(50)
                    ->square(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('try', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->money('try', true)
                    ->sortable()
                    ->color('danger'),

                // If your column is named "stock" in DB, change to ->make('stock')
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable(),


                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([])     // no edit/delete for sellers
            ->bulkActions([]); // none
    }


    public static function getPages(): array
    {
        return [
            // Only list page = read-only
            'index' => Pages\ListProducts::route('/'),
            // Optional: add a read-only View page later if you want
        ];
    }
}
