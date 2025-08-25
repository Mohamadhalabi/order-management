<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon  = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Katalog';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $slug            = 'products';

    // >>> these three make breadcrumbs/titles use TR <<<
    public static function getModelLabel(): string        { return 'Ürün'; }
    public static function getPluralModelLabel(): string  { return 'Ürünler'; }
    public static function getBreadcrumb(): string        { return 'Ürünler'; }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin', 'seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku')->label('SKU')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Ad')->required(),
            Forms\Components\TextInput::make('price')->label('Fiyat (TRY)')->numeric()->required()->default(0),
            Forms\Components\TextInput::make('sale_price')->label('İndirimli Fiyat (TRY)')->numeric()->nullable()
                ->helperText('Boş bırakın veya 0 girin (indirim yok).'),
            Forms\Components\TextInput::make('stock')->label('Stok')->numeric()->required()->default(0),
            Forms\Components\TextInput::make('image')->label('Görsel URL')->nullable(),
        ])->columns(2);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Ad')->searchable()->limit(60),
            Tables\Columns\ImageColumn::make('image')->label('Görsel')->size(48)->square(),
            Tables\Columns\TextColumn::make('price')->label('Fiyat')->money('try', true)->sortable(),
            Tables\Columns\TextColumn::make('sale_price')->label('İndirimli')->money('try', true)->sortable()->color('danger'),
            Tables\Columns\TextColumn::make('stock')->label('Stok')->sortable(),
        ])
        ->actions([Tables\Actions\EditAction::make()->label('Düzenle')])
        ->bulkActions([Tables\Actions\DeleteBulkAction::make()->label('Toplu Sil')])
        ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
