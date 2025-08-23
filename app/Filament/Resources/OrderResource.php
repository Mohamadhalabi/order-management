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

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationGroup = 'Sales';

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
                        Section::make('Order')
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->required()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        $like = "%{$search}%";
                                        return User::query()
                                            ->where(fn ($q) => $q->where('name', 'like', $like)
                                                ->orWhere('email', 'like', $like)
                                                ->orWhere('phone', 'like', $like))
                                            ->limit(50)
                                            ->pluck('name', 'id');
                                    })
                                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                                    ->native(false)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::fillShippingFromCustomer($set, $get)),
                                Select::make('status')
                                    ->options([
                                        'draft'     => 'Draft',
                                        'pending'   => 'Pending',
                                        'paid'      => 'Paid',
                                        'shipped'   => 'Shipped',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->native(false),
                                Textarea::make('notes')->rows(2),
                            ])
                            ->columns(2),

                        Section::make('Items')
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
                                            // IMAGE
                                            ViewComponent::make('filament.components.product-thumb')
                                                ->viewData(function (Get $get) {
                                                    $url = $get('image_url');
                                                    if ($url && ! \str_starts_with($url, 'http')) {
                                                        // adjust disk if needed: ->disk('public')->url($url) or s3, etc.
                                                        $url = \Illuminate\Support\Facades\Storage::url($url);
                                                    }
                                                    return ['url' => $url, 'size' => 72];
                                                })

                                                ->columnSpan(['default' => 12, 'sm' => 2, 'md' => 2, 'lg' => 2, 'xl' => 2]),

                                            // PRODUCT
                                            Select::make('product_id')
                                                ->label('Product')
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

                                                    // prevent duplicates
                                                    $items = $get('../../items') ?: [];
                                                    $instances = 0;
                                                    foreach ($items as $row) {
                                                        if (($row['product_id'] ?? null) == $state) $instances++;
                                                    }
                                                    if ($instances > 1) {
                                                        $set('product_id', null);
                                                        Notification::make()
                                                            ->title('This product is already added to the order.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    // fill dependent fields
                                                    $p = Product::find($state);
                                                    if (!$p) return;

                                                    $set('unit_price', (float) ($p->sale_price ?? $p->price ?? 0));
                                                    $set('stock_snapshot', (int) ($p->stock_quantity ?? 0));
                                                    $set('product_name', $p->name);
                                                    $set('sku', $p->sku);
                                                    $set('image_url', $p->image ?: null); // cache image on item

                                                    self::recalcTotals($set, $get);
                                                }),

                                            // QTY
                                            TextInput::make('qty')
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
                                                    return new HtmlString("<span style=\"{$style}\">Stock: {$stock}</span>");
                                                })
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                            // UNIT PRICE
                                            TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->numeric()
                                                ->default(0)
                                                ->reactive()
                                                ->columnSpan(['default' => 6, 'sm' => 6, 'md' => 6, 'lg' => 6, 'xl' => 6])
                                                ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),

                                            // Hidden cached fields (persisted)
                                            TextInput::make('product_name')->hidden()->dehydrated(),
                                            TextInput::make('sku')->hidden()->dehydrated(),
                                            TextInput::make('stock_snapshot')->hidden()->dehydrated(),
                                        TextInput::make('image_url')
                                            ->hidden()
                                            ->dehydrated()
                                            ->afterStateHydrated(function ($state, Set $set, Get $get) {
                                                // If this existing row has no cached image, pull once from product
                                                if (!$state && ($pid = $get('product_id'))) {
                                                    if ($img = \App\Models\Product::find($pid)?->image) {
                                                        $set('image_url', $img);
                                                    }
                                                }
                                            }),
                                        ]),
                                    ])
                                    ->createItemButtonLabel('Add item')
                                    ->afterStateUpdated(fn (Set $set, Get $get) => self::recalcTotals($set, $get)),
                            ]),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->schema([
                        Section::make('Totals')
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                                TextInput::make('shipping_amount')
                                    ->label('Shipping (TRY)')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->dehydrated(true)
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::recalcTotals($set, $get)),
                                TextInput::make('total')
                                    ->label('Total (TRY)')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->dehydrated(true),
                            ])
                            ->columns(1),

                        Section::make('Customer address')
                            ->schema([
                                Forms\Components\Placeholder::make('customer_address_block')
                                    ->content(function (Get $get) {
                                        $u = $get('customer_id') ? User::find($get('customer_id')) : null;
                                        if (!$u) {
                                            return new HtmlString('<em>Select a customer to see the billing address.</em>');
                                        }

                                        $lines = array_filter([
                                            e($u->name ?? ''),
                                            e($u->billing_address_line1 ?? ''),
                                            e($u->billing_address_line2 ?? ''),
                                            trim(e($u->billing_city ?? '').' '.e($u->billing_state ?? '')),
                                            trim(e($u->billing_postcode ?? '').' '.e($u->billing_country ?? '')),
                                            'Phone: '.e($u->phone ?? ''),
                                        ]);

                                        return new HtmlString('<div style="line-height:1.4">'.implode('<br>', $lines).'</div>');
                                    }),
                            ]),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    protected static function fillShippingFromCustomer(Set $set, Get $get): void
    {
        $u = $get('customer_id') ? User::find($get('customer_id')) : null;
        if (!$u) return;
        // If you have shipping_* columns, map them here.
    }

    protected static function recalcTotals(Set $set, Get $get): void
    {
        // Read items from either root or from inside a row
        $items = $get('../../items') ?? $get('items') ?? [];
        $sub = 0;
        foreach ($items as $row) {
            $qty   = (float)($row['qty'] ?? 0);
            $price = (float)($row['unit_price'] ?? 0);
            $sub  += $qty * $price;
        }

        // Read shipping from root even if we’re in a row
        $ship = (float)($get('../../shipping_amount') ?? $get('shipping_amount') ?? 0);

        // Write back to the ROOT form state no matter the current scope
        self::setRoot($set, 'subtotal', round($sub, 2));
        self::setRoot($set, 'total',    round($sub + $ship, 2));
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending',
                    'success' => 'paid',
                    'info'    => 'shipped',
                    'danger'  => 'cancelled',
                    'gray'    => 'draft',
                ]),
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('shipping_amount')->label('Shipping')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('-')
                    ->toggleable(),  // allow hide/show from column toggler
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->button()         // renders as button (not link)
                    ->outlined()       // outlined style
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
    /** Safely set a ROOT key even when called from inside a repeater row */
    protected static function setRoot(Set $set, string $key, mixed $value): void
    {
        // Try direct path (when already at root)
        $set($key, $value);

        // Also try “go up” paths (when inside repeater rows)
        foreach (["../..", "../../.."] as $prefix) {
            try {
                $set("{$prefix}/{$key}", $value);
            } catch (\Throwable $e) {
                // ignore if not in this depth
            }
        }
    }

}
