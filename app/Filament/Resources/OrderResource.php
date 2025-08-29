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
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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
                                    ->getSearchResultsUsing(function (string $search) {
                                        $like = "%{$search}%";

                                        return User::query()
                                            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'seller'))
                                            ->where(fn ($q) => $q
                                                ->where('name', 'like', $like)
                                                ->orWhere('email', 'like', $like)
                                                ->orWhere('phone', 'like', $like)
                                            )
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
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->label('Ad Soyad')->required(),
                                        Forms\Components\TextInput::make('email')->label('E-posta')->email()
                                            ->rules(['nullable', 'email', 'max:191', \Illuminate\Validation\Rule::unique('users', 'email')]),
                                        Forms\Components\TextInput::make('phone')->label('Telefon')->tel()
                                            ->rules(['nullable', 'string', 'max:20']),
                                        Forms\Components\Fieldset::make('Fatura Adresi')->schema([
                                            Forms\Components\TextInput::make('billing_address_line1')->label('Adres Satırı'),
                                            Forms\Components\TextInput::make('billing_city')->label('Şehir / İlçe'),
                                            Forms\Components\Select::make('billing_state')
                                                ->label('İl (Eyalet)')
                                                ->options(self::turkishProvinces())
                                                ->searchable()->preload()->native(false),
                                            Forms\Components\TextInput::make('billing_postcode')->label('Posta Kodu'),
                                            Forms\Components\TextInput::make('billing_country')->label('Ülke')->default('TR'),
                                        ])->columns(2),
                                    ])
                                    ->createOptionAction(fn (Action $action) => $action->label('Yeni Müşteri Ekle'))
                                    ->createOptionUsing(function (array $data) {
                                        $u = new User();
                                        $u->name  = $data['name'] ?? (explode('@', $data['email'])[0] ?? 'Müşteri');
                                        $u->email = $data['email'] ?? null;
                                        $u->phone = $data['phone'] ?? null;

                                        $u->billing_address_line1 = $data['billing_address_line1'] ?? null;
                                        $u->billing_city          = $data['billing_city'] ?? null;
                                        $u->billing_state         = $data['billing_state'] ?? null;
                                        $u->billing_postcode      = $data['billing_postcode'] ?? null;
                                        $u->billing_country       = $data['billing_country'] ?? 'TR';

                                        $u->password = \Hash::make(Str::random(40));
                                        $u->save();

                                        if (\Spatie\Permission\Models\Role::where('name', 'customer')->exists()) {
                                            $u->assignRole('customer');
                                        }

                                        return $u->getKey();
                                    })
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get))
                                    ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get)),

                                Select::make('status')
                                    ->label('Durum')
                                    ->options([
                                        'taslak'     => 'Taslak',
                                        'onaylandi'  => 'Onaylandı',
                                        'odendi'     => 'Ödendi',
                                        'kargolandi' => 'Kargolandı',
                                        'tamamlandi'  => 'Tamamlandı',
                                        'iptal'      => 'İptal',
                                    ])
                                    ->default('taslak')
                                    ->required()
                                    ->native(false),

                                Textarea::make('notes')->label('Notlar')->rows(2),
                            ])
                            ->columns(2),

                        Section::make('Kalemler')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->live()
                                    ->minItems(1)
                                    ->required()
                                    ->defaultItems(1)
                                    ->collapsible(false)
                                    ->reorderable(false)
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 12, 'md' => 12, 'lg' => 12, 'xl' => 12])
                                            ->extraAttributes(function (Get $get) {
                                                $pid   = $get('product_id');
                                                $stock = (int) ($get('stock_snapshot') ?? 0);
                                                return ($pid && $stock <= 0)
                                                    ? ['style' => 'border:1px solid #dc2626;border-radius:8px;padding:8px;']
                                                    : [];
                                            })
                                            ->schema([
                                                ViewComponent::make('filament.components.product-thumb')
                                                    ->viewData(function (Get $get) {
                                                        $url = $get('image_url');
                                                        if ($url && ! str_starts_with($url, 'http')) {
                                                            $url = Storage::url($url);
                                                        }
                                                        return ['url' => $url, 'size' => 72];
                                                    })
                                                    ->columnSpan(['default' => 12, 'sm' => 2, 'md' => 2, 'lg' => 2, 'xl' => 2]),

                                                Select::make('product_id')
                                                    ->label('Ürün')
                                                    ->required()
                                                    ->searchable()
                                                    ->native(false)
                                                    ->columnSpan(['default' => 12, 'sm' => 10, 'md' => 10, 'lg' => 10, 'xl' => 10])
                                                    ->helperText(function (Get $get) {
                                                        $pid   = $get('product_id');
                                                        $stock = (int) ($get('stock_snapshot') ?? 0);
                                                        if (!$pid) return null;
                                                        if ($stock <= 0) {
                                                            return new HtmlString('<span style="color:#dc2626;font-weight:600;">Bu ürün stokta yok</span>');
                                                        }
                                                        return null;
                                                    })
                                                    ->getSearchResultsUsing(function (string $search) {
                                                        $like = "%{$search}%";
                                                        return Product::query()
                                                            ->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like))
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

                                                        // ✅ Allow duplicates, but warn if this product already exists in other rows
                                                        $items = $get('../../items') ?: [];
                                                        $instances = 0;
                                                        foreach ($items as $row) {
                                                            if (($row['product_id'] ?? null) == $state) $instances++;
                                                        }
                                                        if ($instances > 1) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Bu ürün zaten siparişte mevcut. Yine de eklendi.')
                                                                ->body('Aynı üründen birden fazla kalem eklediniz. İsterseniz adet alanını kullanarak tek satırda toplayabilirsiniz.')
                                                                ->warning()
                                                                ->persistent()   // stays until closed
                                                                ->send();
                                                        }

                                                        // continue filling the row normally
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

                                                        if ($stock <= 0) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Bu ürün stokta yok')
                                                                ->danger()
                                                                ->send();
                                                        }

                                                        self::recalcTotals($set, $get);
                                                    }),
                                                TextInput::make('qty')
                                                    ->label('Adet')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->dehydrated(true)
                                                    // turn blanks into 1, and enforce integer >= 1
                                                    ->dehydrateStateUsing(fn ($state) => ($state === null || $state === '') ? 1 : max(1, (int) $state))
                                                    ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                    ->helperText(function (Get $get) {
                                                        if (!$get('product_id')) return null;
                                                        $stock = (int) $get('stock_snapshot');
                                                        $style = $stock > 0 ? 'color:#16a34a; font-weight:600;' : 'color:#dc2626; font-weight:600;';
                                                        return new \Illuminate\Support\HtmlString("<span style=\"{$style}\">Stok: {$stock}</span>");
                                                    })
                                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),
                                                    
                                                TextInput::make('unit_price')
                                                    ->label('Birim Fiyat')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                    ->reactive()
                                                    ->dehydrated(true)
                                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get))
                                                    ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? 0 : (float)$state),


                                                TextInput::make('product_name')->hidden()->dehydrated(),
                                                TextInput::make('sku')->hidden()->dehydrated(),
                                                TextInput::make('stock_snapshot')->hidden()->dehydrated()
                                                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                        if (!$state && ($pid = $get('product_id'))) {
                                                            $p = Product::find($pid);
                                                            if ($p) $set('stock_snapshot', (int) ($p->stock ?? $p->stock_quantity ?? $p->quantity ?? 0));
                                                        }
                                                    }),
                                                TextInput::make('image_url')->hidden()->dehydrated()
                                                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                        if (!$state && ($pid = $get('product_id'))) {
                                                            if ($img = Product::find($pid)?->image) $set('image_url', $img);
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

                            // 🔻 İNDİRİM % — shows the TRY equivalent
                            TextInput::make('discount_percent')
                                ->label('İndirim %')
                                ->numeric()
                                ->default(0)
                                ->suffix('%')
                                ->reactive()
                                ->dehydrated(true)
                                ->helperText(function (Get $get) {
                                    $sub = (float)($get('subtotal') ?? 0);
                                    $pct = (float)($get('discount_percent') ?? 0);
                                    $amt = round($sub * $pct / 100, 2);
                                    $txt = $sub > 0 ? '≈ ₺ ' . number_format($amt, 2, ',', '.') : '≈ ₺ 0,00';
                                    return new HtmlString('<span class="text-gray-500">'.$txt.'</span>');
                                })
                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                            // 🔻 İNDİRİM (TRY) — shows the % equivalent
                            TextInput::make('discount_amount')
                                ->label('İndirim (TRY)')
                                ->numeric()
                                ->default(0)
                                ->reactive()
                                ->dehydrated(true)
                                ->helperText(function (Get $get) {
                                    $sub = (float)($get('subtotal') ?? 0);
                                    $amt = (float)($get('discount_amount') ?? 0);
                                    $pct = $sub > 0 ? round(($amt * 100) / $sub, 2) : 0;
                                    // use comma decimals to match your UI
                                    $pctStr = rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',');
                                    return new HtmlString('<span class="text-gray-500">≈ '.$pctStr.' %</span>');
                                })
                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

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

                        Section::make('Fatura Adresi')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextInput::make('billing_name')->hidden()->dehydrated(),
                                    TextInput::make('billing_phone')->label('Telefon')->tel()->required()
                                        ->rules(['required', 'string', 'max:20'])->columnSpan(6),
                                    Textarea::make('billing_address_line1')->label('Adres Satırı')->rows(2)->required()->columnSpan(12),
                                    TextInput::make('billing_city')->label('Şehir / İlçe')->columnSpan(12),
                                    Select::make('billing_state')->label('İl (Eyalet)')
                                        ->options(self::turkishProvinces())->searchable()->preload()->native(false)->columnSpan(12)
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
                                    TextInput::make('billing_postcode')->label('Posta Kodu')->columnSpan(12),
                                    TextInput::make('billing_country')->default('TR')->dehydrated()->hidden(),
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

        $set('billing_name', $u->name ?? null);

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

        // ✅ round Subtotal so you don't see 6378.71999999
        $sub = round($sub, 2);

        $kdvPercent      = (float)($get('../../kdv_percent') ?? $get('kdv_percent') ?? 0);
        $discountPercent = (float)($get('../../discount_percent') ?? $get('discount_percent') ?? 0);
        $discountAmount  = (float)($get('../../discount_amount') ?? $get('discount_amount') ?? 0);

        // Percentage-based discount
        $percentDiscount = round($sub * $discountPercent / 100, 2);

        // Final discount uses the higher of amount vs percent (your business rule)
        $finalDiscount = max($discountAmount, $percentDiscount);
        $finalDiscount = min($finalDiscount, $sub); // cannot exceed subtotal

        $kdvAmount = round(($sub - $finalDiscount) * $kdvPercent / 100, 2);

        self::setRoot($set, 'subtotal', $sub);
        self::setRoot($set, 'discount_amount', $finalDiscount);
        self::setRoot($set, 'kdv_amount', $kdvAmount);
        self::setRoot($set, 'total', round($sub - $finalDiscount + $kdvAmount, 2));
    }



    public static function table(Table $table): Table
    {
        $isSeller = auth()->user()?->hasRole('seller') && !auth()->user()?->hasRole('admin');

        return $table
            ->query(fn () => Order::query()
                ->when($isSeller, fn ($q) => $q->where('created_by_id', auth()->id()))
            )
            ->filters([
                \Filament\Tables\Filters\Filter::make('kargolandi')
                    ->label('Kargolandı')
                    ->query(fn ($query) => $query->where('status', 'kargolandi')),

                \Filament\Tables\Filters\Filter::make('tamamlandi')
                    ->label('Tamamlandı')
                    ->query(fn ($query) => $query->where('status', 'tamamlandi')),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Müşteri')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->colors([
                        'gray'    => 'taslak',
                        'success' => ['odendi', 'onaylandi', 'tamamlandi'],
                        'info'    => 'kargolandi',
                        'danger'  => 'iptal',
                    ]),

                // Applied discount % (computed from subtotal & discount_amount)
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('İndirim %')
                    ->state(function (Order $r) {
                        $sub  = (float) ($r->subtotal ?? 0);
                        $disc = (float) ($r->discount_amount ?? 0);
                        if ($sub <= 0) return 0;
                        return round(($disc * 100) / $sub, 2);
                    })
                    ->formatStateUsing(fn ($state) =>
                        rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',')
                    )
                    ->suffix(' %')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('İndirim')
                    ->money('try', true)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Toplam')
                    ->money('try', true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oluşturan')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturma')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->visible(fn ($record) => $record->status !== 'tamamlandi'),

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
            try { $set("{$prefix}/{$key}", $value); } catch (\Throwable $e) {}
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

    /** Recompute totals server-side (used by Create/Edit pages) */

    public static function recomputeTotalsFromArray(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $subtotal = 0.0;

        // check once
        $hasLineTotal = Schema::hasColumn('order_items', 'line_total');

        foreach ($items as $i => $row) {
            $qty   = self::toFloat($row['qty'] ?? 0);
            $price = self::toFloat($row['unit_price'] ?? 0);
            $line  = round($qty * $price, 2);

            $row['qty']        = $qty;
            $row['unit_price'] = $price;

            // only set if column exists
            if ($hasLineTotal) {
                $row['line_total'] = $line;
            } else {
                unset($row['line_total']); // ensure it won't be persisted
            }

            $items[$i] = $row;
            $subtotal += $line;
        }

        $payload['items'] = $items;

        $shipping        = self::toFloat($payload['shipping_amount']   ?? 0);
        $kdvPercent      = self::toFloat($payload['kdv_percent']       ?? 0);
        $discountPercent = self::toFloat($payload['discount_percent']  ?? 0);
        $discountAmount  = self::toFloat($payload['discount_amount']   ?? 0);

        $percentDiscount = round($subtotal * $discountPercent / 100, 2);
        $finalDiscount   = min(max($discountAmount, $percentDiscount), $subtotal);

        $taxBase   = max($subtotal - $finalDiscount, 0);
        $kdvAmount = round($taxBase * $kdvPercent / 100, 2);

        $payload['subtotal']         = self::dec($subtotal);
        $payload['shipping_amount']  = self::dec($shipping);
        $payload['discount_percent'] = self::dec($discountPercent);
        $payload['discount_amount']  = self::dec($finalDiscount);
        $payload['kdv_percent']      = self::dec($kdvPercent);
        $payload['kdv_amount']       = self::dec($kdvAmount);
        $payload['total']            = self::dec($taxBase + $shipping + $kdvAmount);

        return $payload;
    }

    /** Accepts "1.234,56", "1,234.56", "", "  1234 " etc. */
    protected static function toFloat(mixed $value): float
    {
        if (is_null($value) || $value === '') return 0.0;
        if (is_numeric($value)) return (float) $value;

        $s = trim((string) $value);
        if ($s === '') return 0.0;

        // European format 1.234,56
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return (float) $s;
        }

        // Strip thousand separators, spaces
        $s = str_replace([',', ' '], ['', ''], $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** Return a safe decimal string for DB cast. */
    protected static function dec(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

}
