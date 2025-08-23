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
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catalog';

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
            Forms\Components\TextInput::make('sku')
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('name')
                ->required(),

            Forms\Components\TextInput::make('price')
                ->numeric()
                ->required()
                ->default(0),

            Forms\Components\TextInput::make('sale_price')
                ->numeric()
                ->nullable()
                ->helperText('Leave empty or 0 for no discount'),

            // If your column is named "stock" in DB, change to ->make('stock')
            Forms\Components\TextInput::make('stock')
                ->numeric()
                ->required()
                ->default(0),

            // Keep it simple: store an image URL or relative path
            Forms\Components\TextInput::make('image')
                ->label('Image URL')
                ->nullable(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->limit(50),

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


        ])
        ->filters([])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
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