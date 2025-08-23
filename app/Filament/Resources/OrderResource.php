<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationGroup = 'Sales';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin','seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->relationship('customer','name')
                ->searchable()
                ->preload()
                ->native(false),

            Forms\Components\Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'shipped' => 'Shipped',
                    'cancelled' => 'Cancelled',
                ])->default('draft')->required()->native(false),

            Forms\Components\Textarea::make('notes')->rows(2),

            Forms\Components\Section::make('Items')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->defaultItems(0)
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::orderBy('name')->pluck('name','id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(6)
                            ->native(false)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $price = Product::whereKey($state)->value('price') ?? 0;
                                    $set('unit_price', $price);
                                }
                            }),

                        Forms\Components\TextInput::make('qty')
                            ->numeric()->default(1)->minValue(1)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()->rule('decimal:0,2')->default(0)
                            ->columnSpan(4),
                    ])
                    ->createItemButtonLabel('Add item')
                    ->collapsible(),
            ])->collapsed(false),

            Forms\Components\Placeholder::make('computed_total')
                ->label('Total')
                ->content(function (Get $get) {
                    $items = $get('items') ?? [];
                    $sum = 0;
                    foreach ($items as $i) {
                        $q = (int)($i['qty'] ?? 0);
                        $p = (float)($i['unit_price'] ?? 0);
                        $sum += $q * $p;
                    }
                    return number_format($sum, 2);
                }),
        ])->columns(2);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
            Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()
                ->colors([
                    'warning' => 'pending',
                    'success' => 'paid',
                    'info' => 'shipped',
                    'danger' => 'cancelled',
                    'gray' => 'draft',
                ]),
            Tables\Columns\TextColumn::make('total')->money('usd', true)->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->defaultSort('id','desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
