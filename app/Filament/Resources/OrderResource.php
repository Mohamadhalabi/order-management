<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\View as ViewComponent;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    // keep ONLY these once — no duplicates elsewhere in the class
    protected static ?string $navigationGroup   = 'Satışlar';
    protected static ?string $navigationLabel   = 'Siparişler';
    protected static ?string $pluralModelLabel  = 'Siparişler';
    protected static ?string $modelLabel        = 'Sipariş';


    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin', 'seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Sipariş')
                            ->schema([
                            Select::make('customer_id')
                                ->label('Müşteri')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    $like = "%{$search}%";

                                    return \App\Models\User::query()
                                        // EITHER exclude sellers:
                                        ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'seller'))
                                        // OR, if you have a dedicated "customer" role, use this instead:
                                        // ->whereHas('roles', fn ($r) => $r->where('name', 'customer'))
                                        ->where(function ($q) use ($like) {
                                            $q->where('name', 'like', $like)
                                            ->orWhere('email', 'like', $like)
                                            ->orWhere('phone', 'like', $like);
                                        })
                                        ->limit(50)
                                        ->pluck('name', 'id');
                                })
                                ->getOptionLabelUsing(function ($value) {
                                    return \App\Models\User::query()
                                        ->whereKey($value)
                                        ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'seller')) // keep consistent
                                        ->value('name');
                                })
                                ->native(false)
                                ->reactive()
                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get)),

                                Select::make('status')
                                    ->label('Durum')
                                    ->options([
                                        'draft'     => 'Taslak',
                                        'pending'   => 'Beklemede',
                                        'paid'      => 'Ödendi',
                                        'shipped'   => 'Kargolandı',
                                        'cancelled' => 'İptal',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->native(false),

                                Textarea::make('notes')
                                    ->label('Notlar')
                                    ->rows(2),
                            ])
                            ->columns(2),

                        Section::make('Kalemler')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->live()
                                    ->defaultItems(1)
                                    ->collapsible(false)
                                    ->reorderable(false)
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'sm' => 12, 'md' => 12, 'lg' => 12, 'xl' => 12,
                                        ])->schema([
                                            // GÖRSEL
                                            ViewComponent::make('filament.components.product-thumb')
                                                ->viewData(function (Get $get) {
                                                    $url = $get('image_url');
                                                    if ($url && ! \str_starts_with($url, 'http')) {
                                                        $url = Storage::url($url);
                                                    }
                                                    return ['url' => $url, 'size' => 72];
                                                })
                                                ->columnSpan(['default' => 12, 'sm' => 2, 'md' => 2, 'lg' => 2, 'xl' => 2]),

                                            // ÜRÜN
                                            Select::make('product_id')
                                                ->label('Ürün')
                                                ->required()
                                                ->searchable()
                                                ->native(false)
                                                ->columnSpan(['default' => 12, 'sm' => 10, 'md' => 10, 'lg' => 10, 'xl' => 10])
                                                ->getSearchResultsUsing(function (string $search) {
                                                    $like = "%{$search}%";
                                                    return Product::query()
                                                        ->where(fn ($q) => $q->where('sku', 'like', $like)
                                                            ->orWhere('name', 'like', $like))
                                                        ->limit(50)
                                                        ->get()
                                                        ->mapWithKeys(fn ($p) => [$p->id => "{$p->sku} | {$p->name}"])
                                                        ->toArray();
                                                })
                                                ->getOptionLabelUsing(function ($value) {
                                                    $p = Product::find($value);
                                                    return $p ? "{$p->sku} | {$p->name}" : null;
                                                })
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                    if (!$state) return;

                                                    // Aynı ürün eklenmesini engelle
                                                    $items = $get('../../items') ?: [];
                                                    $instances = 0;
                                                    foreach ($items as $row) {
                                                        if (($row['product_id'] ?? null) == $state) $instances++;
                                                    }
                                                    if ($instances > 1) {
                                                        $set('product_id', null);
                                                        Notification::make()
                                                            ->title('Bu ürün zaten siparişe eklendi.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    // Bağımlı alanları doldur
                                                    $p = Product::find($state);
                                                    if (!$p) return;

                                                    $set('unit_price', (float) ($p->sale_price ?? $p->price ?? 0));
                                                    $set('stock_snapshot', (int) ($p->stock_quantity ?? 0));
                                                    $set('product_name', $p->name);
                                                    $set('sku', $p->sku);
                                                    $set('image_url', $p->image ?: null);

                                                    self::recalcTotals($set, $get);
                                                }),

                                            // ADET
                                            TextInput::make('qty')
                                                ->label('Adet')
                                                ->numeric()
                                                ->default(1)
                                                ->minValue(1)
                                                ->reactive()
                                                ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                ->helperText(function (Get $get) {
                                                    $stock = (int) $get('stock_snapshot');
                                                    $style = $stock > 0
                                                        ? 'color:#16a34a; font-weight:600;'
                                                        : 'color:#dc2626; font-weight:600;';
                                                    return new HtmlString("<span style=\"{$style}\">Stok: {$stock}</span>");
                                                })
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                            // BİRİM FİYAT
                                            TextInput::make('unit_price')
                                                ->label('Birim Fiyat')
                                                ->numeric()
                                                ->default(0)
                                                ->reactive()
                                                ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                            // Gizli önbellek alanları
                                            TextInput::make('product_name')->hidden()->dehydrated(),
                                            TextInput::make('sku')->hidden()->dehydrated(),
                                            TextInput::make('stock_snapshot')->hidden()->dehydrated(),
                                            TextInput::make('image_url')
                                                ->hidden()
                                                ->dehydrated()
                                                ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                    if (!$state && ($pid = $get('product_id'))) {
                                                        if ($img = Product::find($pid)?->image) {
                                                            $set('image_url', $img);
                                                        }
                                                    }
                                                }),
                                        ]),
                                    ])
                                    ->createItemButtonLabel('Kalem ekle')
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcTotals($set, $get)),
                            ]),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->schema([
                        Section::make('Toplamlar')
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Ara Toplam (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                                TextInput::make('shipping_amount')
                                    ->label('Kargo (TRY)')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->dehydrated(true)
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),
                                TextInput::make('total')
                                    ->label('Toplam (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                            ])
                            ->columns(1),

                        // ===== Fatura Adresi =====
                        Section::make('Fatura Adresi')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextInput::make('billing_name')
                                        ->label('Ad Soyad')
                                        ->required()
                                        ->columnSpan(6),

                                    TextInput::make('billing_phone')
                                        ->label('Telefon')
                                        ->tel()
                                        ->columnSpan(6),

                                    Textarea::make('billing_address_line1')
                                        ->label('Adres Satırı')
                                        ->required()
                                        ->rows(2)
                                        ->columnSpan(12),

                                    TextInput::make('billing_city')
                                        ->label('Şehir / İlçe')
                                        ->columnSpan(12),

                                    Select::make('billing_state')
                                        ->label('İl (Eyalet)')
                                        ->options(self::turkishProvinces())
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->columnSpan(12)
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

                                    TextInput::make('billing_postcode')
                                        ->label('Posta Kodu')
                                        ->columnSpan(12),

                                    // Ülke her zaman TR (gizli)
                                    TextInput::make('billing_country')
                                        ->default('TR')
                                        ->dehydrated()
                                        ->hidden(),
                                ]),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    protected static function fillBillingFromCustomer(Set $set, Get $get): void
    {
        if (! $get('use_customer_address')) {
            return;
        }

        $u = $get('customer_id') ? User::find($get('customer_id')) : null;
        if (! $u) return;

        $set('billing_name',          $u->name ?? null);
        $set('billing_phone',         $u->phone ?? null);
        $set('billing_address_line1', $u->billing_address_line1 ?? null);
        $set('billing_address_line2', $u->billing_address_line2 ?? null);
        $set('billing_city',          $u->billing_city ?? null);
        $set('billing_state',         $u->billing_state ?? null);
        $set('billing_postcode',      $u->billing_postcode ?? null);
        $set('billing_country',       $u->billing_country ?? 'TR');
    }

    protected static function fillShippingFromCustomer(Set $set, Get $get): void
    {
        // İleride shipping_* alanlarını eklersen buraya benzer eşleme yapılır.
    }

    protected static function recalcTotals(Set $set, Get $get): void
    {
        $items = $get('../../items') ?? $get('items') ?? [];
        $sub = 0;
        foreach ($items as $row) {
            $qty   = (float)($row['qty'] ?? 0);
            $price = (float)($row['unit_price'] ?? 0);
            $sub  += $qty * $price;
        }
        $ship = (float)($get('../../shipping_amount') ?? $get('shipping_amount') ?? 0);
        self::setRoot($set, 'subtotal', round($sub, 2));
        self::setRoot($set, 'total',    round($sub + $ship, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Müşteri')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'info'    => 'shipped',
                        'danger'  => 'cancelled',
                        'gray'    => 'draft',
                    ]),
                Tables\Columns\TextColumn::make('subtotal')->label('Ara Toplam')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('shipping_amount')->label('Kargo')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('total')->label('Toplam')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oluşturan')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    ->size('sm')
                    ->url(fn (Order $r) => $r->pdf_url, true)
                    ->visible(fn (Order $r) => filled($r->pdf_path)),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    protected static function setRoot(Set $set, string $key, mixed $value): void
    {
        $set($key, $value);
        foreach (["../..", "../../.."] as $prefix) {
            try {
                $set("{$prefix}/{$key}", $value);
            } catch (\Throwable $e) {}
        }
    }

    /** @return array<string,string> kod => ad */
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
