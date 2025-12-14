<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\ProductBranchStock;
use App\Models\Currency;
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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter; // <--- Added this
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
                // LEFT
                Group::make()
                    ->schema([
                        Section::make('Sipariş')
                            ->schema([
                                // Şube
                                Select::make('branch_id')
                                    ->label('Şube')
                                    ->required()
                                    ->options(fn () => \App\Models\Branch::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->dehydrated(true)
                                    ->default(function ($record) {
                                        if ($record) return null;
                                        return \App\Models\Branch::orderBy('id')->value('id');
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        self::refreshAllItemStocksForBranch($set, $get, (int) $state);
                                        self::recalcTotals($set, $get);
                                    })
                                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            self::refreshAllItemStocksForBranch($set, $get, (int) $state);
                                        }
                                    }),

                                // Para Birimi (rate comes from Currency table; NOT editable here)
                                Select::make('currency_code')
                                    ->label('Para Birimi')
                                    ->options(fn () => Currency::activeOptions())
                                    // default ONLY on create (no record yet) -> TRY
                                    ->default(fn ($record) => $record?->currency_code ?: 'TRY')
                                    ->required()
                                    ->native(false)
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->afterStateHydrated(function ($state, Set $set, $record) {
                                        // On create with blank state -> force TRY + its rate
                                        if (!$record && blank($state)) {
                                            $set('currency_code', 'TRY');
                                            $set('currency_rate', (float) (Currency::rateFor('TRY') ?? 1));
                                            return;
                                        }

                                        // Otherwise, sync rate with whatever code is present
                                        $code = $state ?: 'TRY';
                                        $set('currency_rate', (float) (Currency::rateFor($code) ?? 1));
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $rate = (float) (Currency::rateFor($state ?: 'TRY') ?? 1);
                                        $set('currency_rate', $rate);
                                        self::repriceAllItemsFromProducts($set, $get, $rate);
                                        self::recalcTotals($set, $get);
                                    }),

                                // Hidden field: stored on order; never edited directly
                                Hidden::make('currency_rate')
                                    ->dehydrated(true)
                                    ->default(fn () => (float) (Currency::rateFor('TRY') ?? 1)),

                                // Müşteri
                                Select::make('customer_id')
                                    ->label('Müşteri')
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->dehydrated(true)
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
                                    ->reactive()
                                    ->createOptionForm([
                                        TextInput::make('name')->label('Ad Soyad')->required(),
                                        TextInput::make('email')->label('E-posta')->email()
                                            ->rules(['nullable', 'email', 'max:191', \Illuminate\Validation\Rule::unique('users', 'email')]),
                                        TextInput::make('phone')->label('Telefon')->tel()
                                            ->rules(['nullable', 'string', 'max:20']),
                                        Forms\Components\Fieldset::make('Fatura Adresi')->schema([
                                            TextInput::make('billing_address_line1')->label('Adres Satırı'),
                                            TextInput::make('billing_city')->label('Şehir / İlçe'),
                                            Select::make('billing_state')
                                                ->label('İl (Eyalet)')
                                                ->options(self::turkishProvinces())
                                                ->searchable()->preload()->native(false),
                                            TextInput::make('billing_postcode')->label('Posta Kodu'),
                                            TextInput::make('billing_country')->label('Ülke')->default('TR'),
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
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::overwriteBillingFromCustomer($set, $get))
                                    ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::fillBillingFromCustomer($set, $get)),

                                Select::make('status')
                                    ->label('Durum')
                                    ->options([
                                        'taslak'     => 'Taslak',
                                        'onaylandi'  => 'Onaylandı',
                                        'odendi'     => 'Ödendi',
                                        'kargolandi' => 'Kargolandı',
                                        'tamamlandi' => 'Tamamlandı',
                                        'iptal'      => 'İptal',
                                    ])
                                    ->default('taslak')
                                    ->required()
                                    ->native(false),

                                Textarea::make('notes')->label('Notlar')->rows(2),
                            ])
                            ->columns(3),

                        Section::make('Kalemler')
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->dehydrated(false)
                                    ->minItems(1)
                                    ->required()
                                    ->defaultItems(1)
                                    ->collapsible(false)
                                    ->reorderable(false)
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 12, 'md' => 12, 'lg' => 12, 'xl' => 12])
                                            ->extraAttributes(function (Get $get) {
                                                $pid = $get('product_id');
                                                if (! $pid) return [];
                                                $branchId = (int) ($get('../../branch_id') ?? $get('branch_id') ?? 0);
                                                $live = self::branchStock($pid, $branchId);
                                                return $live <= 0
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
                                                        $pid = $get('product_id');
                                                        if (! $pid) return null;
                                                        $branchId = (int) ($get('../../branch_id') ?? $get('branch_id') ?? 0);
                                                        $live     = self::branchStock($pid, $branchId);
                                                        if ($live <= 0) {
                                                            return new HtmlString('<span style="color:#dc2626;font-weight:600;">Bu ürün bu şubede stokta yok</span>');
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
                                                        if (! $state) return;

                                                        // duplicate hint (allowed)
                                                        $items = $get('../../items') ?: [];
                                                        $count = 0;
                                                        foreach ($items as $row) {
                                                            if (($row['product_id'] ?? null) == $state) $count++;
                                                        }
                                                        if ($count > 1) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Bu ürün zaten siparişte mevcut.')
                                                                ->body('Aynı ürünü birden fazla kez ekliyorsunuz. Devam etmek istediğinize emin misiniz?')
                                                                ->warning()
                                                                ->persistent()
                                                                ->send();
                                                        }

                                                        $p = Product::find($state);
                                                        if (! $p) return;

                                                        $branchId = (int) ($get('../../branch_id') ?? $get('branch_id') ?? 0);

                                                        // Price in order currency = product USD × selected rate
                                                        $rate = (float) ($get('../../currency_rate') ?? $get('currency_rate') ?? 1);
                                                        $unit = self::unitFromProductAndRate($p, $rate);

                                                        $stock = (int) self::branchStock($p->id, $branchId);
                                                        $img   = $p->image ?: null;

                                                        $set('unit_price', $unit);
                                                        $set('stock_snapshot', $stock);
                                                        $set('product_name', $p->name);
                                                        $set('sku', $p->sku);
                                                        $set('image_url', $img);

                                                        if ($stock <= 0) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Bu ürün bu şubede stokta yok')
                                                                ->danger()->send();
                                                        }

                                                        self::recalcTotals($set, $get);
                                                    }),

                                                TextInput::make('qty')
                                                    ->label('Adet')
                                                    ->numeric()
                                                    ->rule('integer')
                                                    ->required()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->live(onBlur: true)
                                                    ->dehydrateStateUsing(fn ($state) => max(1, (int) ($state ?? 1)))
                                                    ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                    ->helperText(function (Get $get) {
                                                        $pid = $get('product_id');
                                                        if (! $pid) return null;
                                                        $branchId = (int) ($get('../../branch_id') ?? $get('branch_id') ?? 0);
                                                        $live     = self::branchStock($pid, $branchId);
                                                        $style    = $live > 0 ? 'color:#16a34a;font-weight:600;' : 'color:#dc2626;font-weight:600;';
                                                        return new HtmlString("<span style=\"{$style}\">Stok (şube): {$live}</span>");
                                                    })
                                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                                TextInput::make('unit_price')
                                                    ->label('Birim Fiyat')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                                TextInput::make('product_name')->hidden()->dehydrated(),
                                                TextInput::make('sku')->hidden()->dehydrated(),

                                                TextInput::make('stock_snapshot')->hidden()->dehydrated()
                                                    ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                        if ($pid = $get('product_id')) {
                                                            $branchId = (int) ($get('../../branch_id') ?? $get('branch_id') ?? 0);
                                                            $set('stock_snapshot', self::branchStock($pid, $branchId));
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

                // RIGHT
                Group::make()
                    ->schema([
                        Section::make('Toplamlar')
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label(fn (Get $get) => 'Ara Toplam (' . (Currency::symbolFor($get('currency_code')) ?: ($get('currency_code') ?: '')) . ')')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),

                                TextInput::make('shipping_amount')
                                    ->label(fn (Get $get) => 'Kargo (' . (Currency::symbolFor($get('currency_code')) ?: ($get('currency_code') ?: '')) . ')')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->dehydrated(true)
                                    ->afterStateHydrated(fn ($state, Set $set) => $state === null ? $set('shipping_amount', 0) : null)
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : $state)
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => OrderResource::recalcTotals($set, $get)),

                                TextInput::make('discount_percent')
                                    ->label('İndirim %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->dehydrated(true)
                                    ->afterStateHydrated(fn ($state, Set $set) => $state === null ? $set('discount_percent', 0) : null)
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : $state)
                                    // no cross-sync; only totals:
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => OrderResource::recalcTotals($set, $get)),

                                TextInput::make('discount_amount')
                                    ->label(fn (Get $get) => 'İndirim (' . (Currency::symbolFor($get('currency_code')) ?: ($get('currency_code') ?: '')) . ')')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->dehydrated(true)
                                    ->afterStateHydrated(fn ($state, Set $set) => $state === null ? $set('discount_amount', 0) : null)
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : $state)
                                    // no cross-sync; only totals:
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => OrderResource::recalcTotals($set, $get)),

                                TextInput::make('kdv_percent')
                                    ->label('KDV %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->dehydrated(true)
                                    ->afterStateHydrated(fn ($state, Set $set) => $state === null ? $set('kdv_percent', 0) : null)
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? 0 : $state)
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => OrderResource::recalcTotals($set, $get)),

                                TextInput::make('kdv_amount')
                                    ->label(fn (Get $get) => 'KDV (' . (Currency::symbolFor($get('currency_code')) ?: ($get('currency_code') ?: '')) . ')')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),

                                TextInput::make('total')
                                    ->label(fn (Get $get) => 'Toplam (' . (Currency::symbolFor($get('currency_code')) ?: ($get('currency_code') ?: '')) . ')')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                            ])
                            ->columns(2),

                        Section::make('Fatura Adresi')
                            ->schema([
                                Forms\Components\Grid::make(12)->schema([
                                    TextInput::make('billing_name')->hidden()->dehydrated(),
                                    TextInput::make('billing_phone')->label('Telefon')->tel()
                                        ->rules(['string', 'max:20'])->columnSpan(6),
                                    Textarea::make('billing_address_line1')->label('Adres Satırı')->rows(2)->columnSpan(12),
                                    TextInput::make('billing_city')->label('Şehir / İlçe')->columnSpan(12),
                                    Select::make('billing_state')->label('İl (Eyalet)')
                                        ->options(self::turkishProvinces())->searchable()->preload()->native(false)->columnSpan(12)
                                        ->afterStateHydrated(function ($state, Set $set) {
                                            if (blank($state)) return;
                                            if (! str_starts_with((string) $state, 'TR')) {
                                                $nameToCode = [];
                                                foreach (self::turkishProvinces() as $code => $name) {
                                                    $nameToCode[\Illuminate\Support\Str::of($name)->lower()] = $code;
                                                }
                                                $code = $nameToCode[\Illuminate\Support\Str::of((string) $state)->lower()] ?? $state;
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

    /** Refresh ALL item stock_snapshots when branch changes. */
    protected static function refreshAllItemStocksForBranch(Set $set, Get $get, int $branchId): void
    {
        $items = $get('items') ?? [];
        foreach ($items as $i => $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if (! $pid) continue;
            $stock = self::branchStock($pid, $branchId);
            $set("items.{$i}.stock_snapshot", $stock);
        }
    }

    /** Price helper: product USD × rate → rounded(2). */
    protected static function unitFromProductAndRate(?Product $p, float $rate): float
    {
        if (! $p) return 0.0;
        $usd = (float) ($p->sale_price ?? $p->price ?? 0);
        return round($usd * max(0, $rate), 2);
    }

    /** Recompute all item unit prices from product.usd × rate (called on currency change). */
    public static function repriceAllItemsFromProducts(Set $set, Get $get, float $rate): void
    {
        $items = $get('items') ?? [];
        foreach ($items as $i => $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if (! $pid) continue;
            $p = Product::find($pid);
            $unit = self::unitFromProductAndRate($p, $rate);
            $set("items.{$i}.unit_price", $unit);
        }
    }

    /** Return stock for product at branch (0 if none). */
    protected static function branchStock(int $productId, ?int $branchId): int
    {
        if (! $productId || ! $branchId) return 0;
        return (int) (ProductBranchStock::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->value('stock') ?? 0);
    }

    /** Prefill billing only when fields are blank (on hydrate). */
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

    /** Overwrite billing when user explicitly changes the customer. */
    protected static function overwriteBillingFromCustomer(Set $set, Get $get): void
    {
        $customerId = $get('customer_id');
        if (! $customerId) return;

        $u = User::find($customerId);
        if (! $u) return;

        $state = $u->billing_state ?? null;
        if ($state && ! str_starts_with((string) $state, 'TR')) {
            $map = [];
            foreach (self::turkishProvinces() as $code => $name) {
                $map[mb_strtolower($name)] = $code;
            }
            $state = $map[mb_strtolower($state)] ?? $state;
        }

        $set('billing_name',          $u->name ?? null);
        $set('billing_phone',         $u->phone ?? null);
        $set('billing_address_line1', $u->billing_address_line1 ?? null);
        $set('billing_address_line2', $u->billing_address_line2 ?? null);
        $set('billing_city',          $u->billing_city ?? null);
        $set('billing_state',         $state);
        $set('billing_postcode',      $u->billing_postcode ?? null);
        $set('billing_country',       $u->billing_country ?? 'TR');
    }

    protected static function recalcTotals(Set $set, Get $get): void
    {
        usleep(400000);

        $items = $get('../../items') ?? $get('items') ?? [];
        $sub = 0.0;

        foreach ($items as $row) {
            $qty   = (float) ($row['qty'] ?? 0);
            $price = (float) ($row['unit_price'] ?? 0);
            $sub  += $qty * $price;
        }

        $sub = round($sub, 2);

        $kdvPercent      = (float) ($get('../../kdv_percent')      ?? $get('kdv_percent')      ?? 0);
        $discountPercent = (float) ($get('../../discount_percent') ?? $get('discount_percent') ?? 0);
        $discountAmount  = (float) ($get('../../discount_amount')  ?? $get('discount_amount')  ?? 0);
        $shippingAmount  = (float) ($get('../../shipping_amount')  ?? $get('shipping_amount')  ?? 0);

        // Pick the larger of percent or amount (but DO NOT mutate the fields),
        // then cap by subtotal:
        $percentDiscount = round($sub * $discountPercent / 100, 2);
        $finalDiscount   = min(max($discountAmount, $percentDiscount), $sub);

        $taxBase   = max($sub - $finalDiscount, 0);
        $kdvAmount = round($taxBase * $kdvPercent / 100, 2);

        self::setRoot($set, 'subtotal', $sub);
        self::setRoot($set, 'kdv_amount', $kdvAmount);
        self::setRoot($set, 'total', round($taxBase + $shippingAmount + $kdvAmount, 2));
    }

    public static function table(Table $table): Table
    {
        $isSeller = auth()->user()?->hasRole('seller') && ! auth()->user()?->hasRole('admin');

        return $table
            ->query(fn () => Order::query()
                ->when($isSeller, fn ($q) => $q->where('created_by_id', auth()->id()))
            )
            ->filters([
                // -------- NEW: SELLER FILTER (Only for Admin) --------
                SelectFilter::make('created_by_id')
                    ->label('Satış Temsilcisi')
                    ->searchable()
                    ->options(fn () => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->pluck('name', 'id'))
                    ->visible(! $isSeller), // Hide if user is a seller (they see only own orders anyway)
                // -----------------------------------------------------

                Filter::make('durumlar')
                    ->label('Durum')
                    ->form([
                        Forms\Components\Toggle::make('show_kargolandi')->label('Kargolandı')->inline(false),
                        Forms\Components\Toggle::make('show_tamamlandi')->label('Tamamlandı')->inline(false),
                    ])
                    ->columns(2)
                    ->default([
                        'show_kargolandi' => false,
                        'show_tamamlandi' => false,
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $selected = [];
                        if (!empty($data['show_kargolandi']))  $selected[] = 'kargolandi';
                        if (!empty($data['show_tamamlandi']))  $selected[] = 'tamamlandi';

                        if (empty($selected)) {
                            return $query->whereNotIn('status', ['kargolandi', 'tamamlandi']);
                        }
                        return $query->whereIn('status', $selected);
                    })
                    ->indicateUsing(function (array $data): array {
                        $chips = [];
                        if (!empty($data['show_kargolandi'])) $chips[] = 'Yalnızca: Kargolandı';
                        if (!empty($data['show_tamamlandi'])) $chips[] = 'Yalnızca: Tamamlandı';
                        return $chips;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Müşteri')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Durum')->badge()
                    ->colors([
                        'gray'    => 'taslak',
                        'success' => ['odendi', 'onaylandi', 'tamamlandi'],
                        'info'    => 'kargolandi',
                        'danger'  => 'iptal',
                    ]),
                Tables\Columns\TextColumn::make('branch.name')->label('Şube'),
                Tables\Columns\TextColumn::make('currency_code')
                    ->label('PB')
                    ->state(fn (Order $r) => $r->currency_code ?? '')
                    ->badge(),
                Tables\Columns\TextColumn::make('kdv_percent')
                    ->label('KDV %')
                    ->state(fn (Order $r) => (float) ($r->kdv_percent ?? 0))
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ','))
                    ->suffix(' %')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('İndirim')
                    ->state(function (Order $r) {
                        $sym = Currency::symbolFor($r->currency_code) ?: $r->currency_code;
                        return ($sym ? "{$sym} " : '') . number_format((float) $r->discount_amount, 2, '.', ',');
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Toplam')
                    ->state(function (Order $r) {
                        $sym = Currency::symbolFor($r->currency_code) ?: $r->currency_code;
                        return ($sym ? "{$sym} " : '') . number_format((float) $r->total, 2, '.', ',');
                    }),
                Tables\Columns\TextColumn::make('creator.name')->label('Oluşturan')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Oluşturma')->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle')->visible(fn ($record) => $record->status !== 'tamamlandi'),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->button()
                    ->extraAttributes(['style' => 'background-color:#2D83B0;color:#fff'])
                    ->url(fn ($record) => route('orders.pdf', $record), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Siparişi Sil')
                    ->modalDescription('Bu işlem geri alınamaz. Siparişi kalıcı olarak silmek istediğinizden emin misiniz?')
                    ->visible(fn ($record) => $record->status !== 'tamamlandi'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('set_status')
                    ->label('Durumu Değiştir')
                    ->icon('heroicon-o-adjustments-vertical')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Seçilen siparişlerin durumunu değiştir')
                    ->modalButton('Uygula')
                    ->form([
                        Select::make('status')
                            ->label('Yeni durum')
                            ->required()
                            ->native(false)
                            ->options([
                                'taslak'     => 'Taslak',
                                'onaylandi'  => 'Onaylandı',
                                'odendi'     => 'Ödendi',
                                'kargolandi' => 'Kargolandı',
                                'tamamlandi' => 'Tamamlandı',
                                'iptal'      => 'İptal',
                            ]),
                    ])
                    ->action(function (array $data, \Illuminate\Database\Eloquent\Collection $records) {
                        $updated = 0;
                        $skipped = 0;

                        foreach ($records as $order) {
                            if (($order->status ?? null) === 'tamamlandi') {
                                $skipped++;
                                continue;
                            }

                            $order->status = $data['status'];

                            if (!empty($data['note'])) {
                                if (!empty($data['overwrite_note'])) {
                                    $order->notes = $data['note'];
                                } else {
                                    $order->notes = trim(
                                        ($order->notes ? $order->notes . PHP_EOL : '') . $data['note']
                                    );
                                }
                            }

                            $order->saveQuietly();
                            $updated++;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Toplu durum güncellemesi')
                            ->body("Güncellenen: {$updated}" . ($skipped ? " · Atlanan (tamamlandı): {$skipped}" : ''))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\DeleteBulkAction::make()
                    ->label('Seçilenleri Sil')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Seçilen Siparişleri Sil')
                    ->modalDescription('Bu işlem geri alınamaz. Seçilen siparişleri silmek istediğinizden emin misiniz?'),
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

    public static function recomputeTotalsFromArray(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $subtotal = 0.0;

        $hasLineTotal = Schema::hasColumn('order_items', 'line_total');

        foreach ($items as $i => $row) {
            $qty   = self::toFloat($row['qty'] ?? 0);
            $price = self::toFloat($row['unit_price'] ?? 0);
            $line  = round($qty * $price, 2);

            $row['qty']        = $qty;
            $row['unit_price'] = $price;

            if ($hasLineTotal) {
                $row['line_total'] = $line;
            } else {
                unset($row['line_total']);
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

    protected static function toFloat(mixed $value): float
    {
        if (is_null($value) || $value === '') return 0.0;
        if (is_numeric($value)) return (float) $value;

        $s = trim((string) $value);
        if ($s === '') return 0.0;

        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return (float) $s;
        }

        $s = str_replace([',', ' '], ['', ''], $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    protected static function dec(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }
}