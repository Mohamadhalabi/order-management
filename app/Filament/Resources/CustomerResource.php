<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?string $slug = 'customers';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin', 'seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }


    /** Show only Woo customers (wc_id not null). Remove this if you want ALL users. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('wc_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')->label('First name')->maxLength(100),
            Forms\Components\TextInput::make('last_name')->label('Last name')->maxLength(100),
            Forms\Components\TextInput::make('name')->label('Display name')->maxLength(150),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone')->maxLength(50),
            Forms\Components\TextInput::make('password')->password()->revealable()->nullable()
                ->helperText('Leave empty to auto‑generate a random password.'),
            Forms\Components\TextInput::make('wc_id')->numeric()->nullable()
                ->helperText('Optional WooCommerce ID; leave blank for local‑only customers.'),

            Fieldset::make('Billing address')->schema([
                Forms\Components\TextInput::make('address_line1')->label('Address line 1')->maxLength(255),
                Forms\Components\TextInput::make('address_line2')->label('Address line 2')->maxLength(255),
                Forms\Components\TextInput::make('city')->maxLength(120),
                Forms\Components\TextInput::make('state')->label('State / Region')->maxLength(120),
                Forms\Components\TextInput::make('postcode')->label('Postcode')->maxLength(32),
                Forms\Components\TextInput::make('country')->helperText('2‑letter code (e.g. TR, US)')->maxLength(2),
            ])->columns(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist->schema([
            \Filament\Infolists\Components\Section::make('Customer')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('name'),
                    \Filament\Infolists\Components\TextEntry::make('email'),
                    \Filament\Infolists\Components\TextEntry::make('phone'),
                    \Filament\Infolists\Components\TextEntry::make('wc_id')->label('WooCommerce ID'),
                    \Filament\Infolists\Components\TextEntry::make('wc_synced_at')->dateTime()->label('Synced At'),
                    \Filament\Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    \Filament\Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                ])->columns(2),

            \Filament\Infolists\Components\Section::make('Billing address')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('billing_address_line1')->label('Line 1'),
                    \Filament\Infolists\Components\TextEntry::make('billing_address_line2')->label('Line 2'),
                    \Filament\Infolists\Components\TextEntry::make('billing_city')->label('City'),
                    \Filament\Infolists\Components\TextEntry::make('billing_state')->label('State / Region'),
                    \Filament\Infolists\Components\TextEntry::make('billing_postcode')->label('Postcode'),
                    \Filament\Infolists\Components\TextEntry::make('billing_country')->label('Country'),
                ])->columns(2),
        ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view'  => Pages\ViewCustomer::route('/{record}'),
            'edit'  => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
