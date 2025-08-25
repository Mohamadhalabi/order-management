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

    protected static ?string $navigationGroup = 'Katalog';
    protected static ?string $navigationIcon  = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $slug            = 'products';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ad')->searchable()->limit(60),
                Tables\Columns\ImageColumn::make('image')->label('Görsel')->size(48)->square(),
                Tables\Columns\TextColumn::make('price')->label('Fiyat')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('sale_price')->label('İndirimli')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('stock')->label('Stok')->sortable(),
            ])
            ->actions([])      // satıcılar için sadece görüntüleme
            ->bulkActions([]); // yok
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
        ];
    }
}
