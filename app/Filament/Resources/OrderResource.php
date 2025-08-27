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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

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
                                    // SEARCH (exclude sellers)
                                    ->getSearchResultsUsing(function (string $search) {
                                        $like = "%{$search}%";

                                        return User::query()
                                            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'seller'))
                                            ->where(function ($q) use ($like) {
                                                $q->where('name', 'like', $like)
                                                  ->orWhere('email', 'like', $like)
                                                  ->orWhere('phone', 'like', $like);
                                            })
                                            ->limit(50)
                                            ->pluck('name', 'id');
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        return User::query()
                                            ->whereKey($value)
                                            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'seller'))
                                            ->value('name');
                                    })
                                    ->native(false)
                                    ->reactive()
                                    // Inline create customer
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->label('Ad Soyad')->required(),
                                        Forms\Components\TextInput::make('email')
                                            ->label('E-posta')
                                            ->required()
                                            ->email()
                                            ->rules(['email', 'max:191', \Illuminate\Validation\Rule::unique('users', 'email')]),
                                        Forms\Components\TextInput::make('phone')->label('Telefon'),

                                        Forms\Components\Fieldset::make('Fatura Adresi')
                                            ->schema([
                                                Forms\Components\TextInput::make('billing_address_line1')->label('Adres Satırı'),
                                                Forms\Components\TextInput::make('billing_city')->label('Şehir / İlçe'),
                                                Forms\Components\Select::make('billing_state')
                                                    ->label('İl (Eyalet)')
                                                    ->options(self::turkishProvinces())
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false),
                                                Forms\Components\TextInput::make('billing_postcode')->label('Posta Kodu'),
                                                Forms\Components\TextInput::make('billing_country')->label('Ülke')->default('TR'),
                                            ])->columns(2),
                                    ])
                                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                        // nicer label
                                        return $action->label('Yeni Müşteri Ekle');
                                    })
                                    ->createOptionUsing(function (array $data) {
                                        $u = new User();
                                        $u->name  = $data['name'] ?? (explode('@', $data['email'])[0] ?? 'Müşteri');
                                        $u->email = $data['email'];
                                        $u->phone = $data['phone'] ?? null;

                                        $u->billing_address_line1 = $data['billing_address_line1'] ?? null;
                                        $u->billing_city          = $data['billing_city'] ?? null;
                                        $u->billing_state         = $data['billing_state'] ?? null;
                                        $u->billing_postcode      = $data['billing_postcode'] ?? null;
                                        $u->billing_country       = $data['billing_country'] ?? 'TR';

                                        // random placeholder password
                                        $u->password = Hash::make(Str::random(40));
                                        $u->save();

                                        // Assign "customer" role if it exists (optional)
                                        if (\Spatie\Permission\Models\Role::where('name', 'customer')->exists()) {
                                            $u->assignRole('customer');
                                        }

                                        return $u->getKey();
                                    })
                                    // Fill billing on select & on load
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get))
                                    ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get)),

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

                                                    // Aynı ürünü iki kez ekleme
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

                                                    $p = Product::find($state);
                                                    if (!$p) return;

                                                    $unit  = (float) ($p->sale_price ?? $p->price ?? 0);
                                                    $stock = (int) ($p->stock ?? $p->stock_quantity ?? $p->quantity ?? 0);
                                                    $img   = $p->image ?: null;

                                                    $set('unit_price', $unit);
                                                    $set('stock_snapshot', $stock);
                                                    $set('product_name', $p->name);
                                                    $set('sku', $p->sku);
                                                    $set('image_url', $img);

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

                                            // Gizli cache alanları
                                            TextInput::make('product_name')->hidden()->dehydrated(),
                                            TextInput::make('sku')->hidden()->dehydrated(),
                                            TextInput::make('stock_snapshot')
                                                ->hidden()
                                                ->dehydrated()
                                                ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                    if (!$state && ($pid = $get('product_id'))) {
                                                        $p = Product::find($pid);
                                                        if ($p) {
                                                            $set('stock_snapshot', (int) ($p->stock ?? $p->stock_quantity ?? $p->quantity ?? 0));
                                                        }
                                                    }
                                                }),
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

                                // NEW: KDV
                                TextInput::make('kdv_percent')
                                    ->label('KDV %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->reactive()
                                    ->dehydrated(true)
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                TextInput::make('kdv_amount')
                                    ->label('KDV (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),

                                TextInput::make('total')
                                    ->label('Toplam (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                            ])
                            ->columns(2),

                        // ===== Fatura Adresi =====
                        Section::make('Fatura Adresi')
                            ->schema([
                                Grid::make(12)->schema([
                                    // use customer name (hidden but saved)
                                    TextInput::make('billing_name')->hidden()->dehydrated(),

                                    TextInput::make('billing_phone')
                                        ->label('Telefon')
                                        ->tel()
                                        ->columnSpan(6),

                                    Textarea::make('billing_address_line1')
                                        ->label('Adres Satırı')
                                        ->rows(2)
                                        ->required()
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
                                        ->afterStateHydrated(function ($state, Set $set) {
                                            if (blank($state)) return;
                                            if (! str_starts_with((string) $state, 'TR')) {
                                                $nameToCode = [];
                                                foreach (self::turkishProvinces() as $code => $name) {
                                                    $nameToCode[mb_strtolower($name)] = $code;
                                                }
                                                $code = $nameToCode[mb_strtolower($state)] ?? $state;
                                                $set('billing_state', $code);
                                            }
                                        }),

                                    TextInput::make('billing_postcode')
                                        ->label('Posta Kodu')
                                        ->columnSpan(12),

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
        $customerId = $get('customer_id');
        if (! $customerId) return;

        $u = User::find($customerId);
        if (! $u) return;

        // Always use customer's name
        $set('billing_name', $u->name ?? null);

        // Fill others only if blank (preserve manual edits)
        $fillIfBlank = function (string $key, $value) use ($set, $get) {
            if (blank($get($key))) $set($key, $value);
        };

        $fillIfBlank('billing_phone',         $u->phone ?? null);
        $fillIfBlank('billing_address_line1', $u->billing_address_line1 ?? null);
        $fillIfBlank('billing_address_line2', $u->billing_address_line2 ?? null);
        $fillIfBlank('billing_city',          $u->billing_city ?? null);

        $state = $u->billing_state ?? null;
        if ($state && ! str_starts_with((string) $state, 'TR')) {
            $map = [];
            foreach (self::turkishProvinces() as $code => $name) {
                $map[mb_strtolower($name)] = $code;
            }
            $state = $map[mb_strtolower($state)] ?? $state;
        }
        $fillIfBlank('billing_state',    $state);
        $fillIfBlank('billing_postcode', $u->billing_postcode ?? null);
        $fillIfBlank('billing_country',  $u->billing_country ?? 'TR');
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

        // NEW: KDV
        $kdvPercent = (float)($get('../../kdv_percent') ?? $get('kdv_percent') ?? 0);
        $kdvAmount  = round($sub * $kdvPercent / 100, 2);

        self::setRoot($set, 'subtotal',   round($sub, 2));
        self::setRoot($set, 'kdv_amount', $kdvAmount);
        self::setRoot($set, 'total',      round($sub + $ship + $kdvAmount, 2));
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
                Tables\Columns\TextColumn::make('kdv_amount')->label('KDV')->money('try', true)->sortable(),
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
                    ->icon('heroicon-o-document-text')
                    ->button()
                    ->extraAttributes(['style' => 'background-color:#2D83B0;color:#fff'])
                    ->url(fn ($record) => route('orders.pdf', $record), shouldOpenInNewTab: true),
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
        foreach (['../..', '../../..'] as $prefix) {
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

    /**
     * Server-side totals calculator used by CreateOrder/EditOrder.
     * Accepts the whole form payload (including items) and returns the
     * payload with normalized numbers + computed subtotal, kdv_amount, total.
     */
    public static function recomputeTotalsFromArray(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $subtotal = 0.0;

        foreach ($items as $i => $row) {
            $qty   = self::toFloat($row['qty'] ?? 0);
            $price = self::toFloat($row['unit_price'] ?? 0);
            $line  = round($qty * $price, 2);

            // Normalize back into items (useful if you store these fields)
            $row['qty']        = $qty;
            $row['unit_price'] = $price;
            $row['line_total'] = $line;

            $items[$i] = $row;
            $subtotal += $line;
        }

        $payload['items'] = $items;

        $shipping    = self::toFloat($payload['shipping_amount'] ?? 0);
        $kdvPercent  = self::toFloat($payload['kdv_percent'] ?? 0);
        $kdvAmount   = round($subtotal * $kdvPercent / 100, 2);

        $payload['subtotal']   = round($subtotal, 2);
        $payload['kdv_amount'] = $kdvAmount;
        $payload['total']      = round($subtotal + $shipping + $kdvAmount, 2);

        return $payload;
    }

    /**
     * Accepts strings like "1.234,56", "1,234.56", "  1234 " and returns a float.
     */
    protected static function toFloat(mixed $value): float
    {
        if (is_null($value)) return 0.0;
        if (is_numeric($value)) return (float) $value;

        $s = trim((string) $value);

        // If it uses comma as decimal (e.g., 1.234,56)
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $s = str_replace('.', '', $s);   // remove thousands dots
            $s = str_replace(',', '.', $s);  // decimal comma -> dot
            return (float) $s;
        }

        // Otherwise remove thousands commas and spaces
        $s = str_replace([',', ' '], ['', ''], $s);
        return (float) $s;
    }
}
