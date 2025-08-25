<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Satışlar';
    protected static ?string $navigationLabel = 'Müşteriler';
    protected static ?string $slug            = 'customers';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin', 'seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Tümü görünsün; yerel oluşturduklarını da listeleyebilmek için filtre YOK. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'seller'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Kimlik
            TextInput::make('first_name')->label('Ad')->maxLength(100),
            TextInput::make('last_name')->label('Soyad')->maxLength(100),
            TextInput::make('name')->label('Görünen İsim')->maxLength(150),

            // İletişim
            TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Telefon')->maxLength(50),

            // Şifre
            TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->revealable()
                ->nullable()
                ->helperText('Boş bırakırsanız rastgele bir şifre oluşturulur.'),

            // Woo
            TextInput::make('wc_id')
                ->label('WooCommerce ID')
                ->numeric()
                ->nullable()
                ->helperText('Opsiyonel; sadece Woo müşterileri için.'),

            // Fatura Adresi (billing_* kolonu ile EŞLEŞİK)
            Fieldset::make('Fatura adresi')->schema([
                TextInput::make('billing_address_line1')->label('Adres Satırı 1')->maxLength(255),
                TextInput::make('billing_address_line2')->label('Adres Satırı 2')->maxLength(255),

                TextInput::make('billing_city')->label('Şehir / İlçe')->maxLength(120),

                Select::make('billing_state')
                    ->label('İl (Eyalet)')
                    ->options(self::turkishProvinces())    // TR01 => Adana, ...
                    ->searchable()
                    ->preload()
                    ->native(false)
                    // Eski kayıtlar isim tutuyorsa koda çevir:
                    ->afterStateHydrated(function ($state, \Filament\Forms\Set $set) {
                        if (blank($state)) return;
                        if (! str_starts_with((string) $state, 'TR')) {
                            $nameToCode = [];
                            foreach (self::turkishProvinces() as $code => $name) {
                                $nameToCode[mb_strtolower($name)] = $code;
                            }
                            $code = $nameToCode[mb_strtolower($state)] ?? null;
                            if ($code) $set('billing_state', $code);
                        }
                    }),

                TextInput::make('billing_postcode')->label('Posta Kodu')->maxLength(32),

                TextInput::make('billing_country')
                    ->label('Ülke (2 harf)')
                    ->helperText('Örn: TR, US')
                    ->maxLength(2),
            ])->columns(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturma')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Görüntüle'),
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Toplu Sil'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Müşteri')
                ->schema([
                    TextEntry::make('name')->label('Ad Soyad'),
                    TextEntry::make('email')->label('E-posta'),
                    TextEntry::make('phone')->label('Telefon'),
                    TextEntry::make('wc_id')->label('WooCommerce ID'),
                    TextEntry::make('wc_synced_at')->label('Eşitlendi')->dateTime(),
                    TextEntry::make('created_at')->label('Oluşturma')->dateTime(),
                    TextEntry::make('updated_at')->label('Güncelleme')->dateTime(),
                ])->columns(2),

            InfoSection::make('Fatura adresi')
                ->schema([
                    TextEntry::make('billing_address_line1')->label('Satır 1'),
                    TextEntry::make('billing_address_line2')->label('Satır 2'),
                    TextEntry::make('billing_city')->label('Şehir / İlçe'),
                    TextEntry::make('billing_state')->label('İl (Eyalet)'),
                    TextEntry::make('billing_postcode')->label('Posta Kodu'),
                    TextEntry::make('billing_country')->label('Ülke'),
                ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view'   => Pages\ViewCustomer::route('/{record}'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    /** @return array<string,string> TR kodu => İl adı */
    protected static function turkishProvinces(): array
    {
        return [
            'TR01' => 'Adana','TR02' => 'Adıyaman','TR03' => 'Afyonkarahisar','TR04' => 'Ağrı','TR05' => 'Amasya',
            'TR06' => 'Ankara','TR07' => 'Antalya','TR08' => 'Artvin','TR09' => 'Aydın','TR10' => 'Balıkesir',
            'TR11' => 'Bilecik','TR12' => 'Bingöl','TR13' => 'Bitlis','TR14' => 'Bolu','TR15' => 'Burdur',
            'TR16' => 'Bursa','TR17' => 'Çanakkale','TR18' => 'Çankırı','TR19' => 'Çorum','TR20' => 'Denizli',
            'TR21' => 'Diyarbakır','TR22' => 'Edirne','TR23' => 'Elazığ','TR24' => 'Erzincan','TR25' => 'Erzurum',
            'TR26' => 'Eskişehir','TR27' => 'Gaziantep','TR28' => 'Giresun','TR29' => 'Gümüşhane','TR30' => 'Hakkâri',
            'TR31' => 'Hatay','TR32' => 'Isparta','TR33' => 'Mersin','TR34' => 'İstanbul','TR35' => 'İzmir',
            'TR36' => 'Kars','TR37' => 'Kastamonu','TR38' => 'Kayseri','TR39' => 'Kırklareli','TR40' => 'Kırşehir',
            'TR41' => 'Kocaeli','TR42' => 'Konya','TR43' => 'Kütahya','TR44' => 'Malatya','TR45' => 'Manisa',
            'TR46' => 'Kahramanmaraş','TR47' => 'Mardin','TR48' => 'Muğla','TR49' => 'Muş','TR50' => 'Nevşehir',
            'TR51' => 'Niğde','TR52' => 'Ordu','TR53' => 'Rize','TR54' => 'Sakarya','TR55' => 'Samsun',
            'TR56' => 'Siirt','TR57' => 'Sinop','TR58' => 'Sivas','TR59' => 'Tekirdağ','TR60' => 'Tokat',
            'TR61' => 'Trabzon','TR62' => 'Tunceli','TR63' => 'Şanlıurfa','TR64' => 'Uşak','TR65' => 'Van',
            'TR66' => 'Yozgat','TR67' => 'Zonguldak','TR68' => 'Aksaray','TR69' => 'Bayburt','TR70' => 'Karaman',
            'TR71' => 'Kırıkkale','TR72' => 'Batman','TR73' => 'Şırnak','TR74' => 'Bartın','TR75' => 'Ardahan',
            'TR76' => 'Iğdır','TR77' => 'Yalova','TR78' => 'Karabük','TR79' => 'Kilis','TR80' => 'Osmaniye','TR81' => 'Düzce',
        ];
    }
}
