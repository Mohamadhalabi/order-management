<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class SellerResource extends Resource
{
    protected static ?string $model = User::class;

    // Turkish nav
    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Satışlar';
    protected static ?string $navigationLabel = 'Satıcılar';
    protected static ?string $slug            = 'sellers';

    public static function getModelLabel(): string        { return 'Satıcı'; }
    public static function getPluralModelLabel(): string  { return 'Satıcılar'; }

    /** Only admins can see/manage sellers */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** 🔧 Only users with the `seller` role */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn ($r) => $r->where('name', 'seller'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('Ad Soyad')->required()->maxLength(255),
            TextInput::make('email')->label('E-posta')->required()->email()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
            TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn ($state) => filled($state)),
            Toggle::make('active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ad Soyad')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->label('E-posta')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon'),
                Tables\Columns\IconColumn::make('active')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->dateTime()->since()->sortable(),
            ])
            // ❗ Remove table header create action to avoid duplicates
            // ->headerActions([ Tables\Actions\CreateAction::make(), ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit'   => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
