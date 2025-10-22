<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    /** ✅ Sidebar settings */
    protected static ?string $navigationLabel = 'Currencies';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 2;

    /** ✅ Force it to appear in the menu */
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /** ✅ Don’t hide behind policies/roles (adjust if you want gating) */
    public static function canViewAny(): bool
    {
        return true; // or: auth()->check() && auth()->user()->hasAnyRole(['admin','seller'])
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Kod')
                ->required()->minLength(3)->maxLength(3)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('Ad')->required(),
            Forms\Components\TextInput::make('symbol')->label('Sembol'),
            Forms\Components\TextInput::make('rate')->label('Kur (USD baz)')
                ->required()->numeric()->default(1),
            Forms\Components\Toggle::make('is_default')->label('Varsayılan')
                ->inline(false)
                ->afterStateUpdated(function ($state, $record) {
                    if ($state) \App\Models\Currency::whereKeyNot($record?->id)->update(['is_default' => false]);
                }),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->inline(false)->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Kod')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ad')->searchable(),
                Tables\Columns\TextColumn::make('symbol')->label('Sembol'),
                Tables\Columns\TextColumn::make('rate')
                    ->label('Kur')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')->label('Varsayılan')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit'   => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
