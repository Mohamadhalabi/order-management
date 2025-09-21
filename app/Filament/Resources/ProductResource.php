<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchStock;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon  = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Katalog';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $slug            = 'products';

    public static function getModelLabel(): string       { return 'Ürün'; }
    public static function getPluralModelLabel(): string { return 'Ürünler'; }
    public static function getBreadcrumb(): string       { return 'Ürünler'; }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole('admin', 'seller') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Eager-load branchStocks to avoid N+1. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('branchStocks');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        // Build inputs for each branch
        $branchStockInputs = [];
        foreach (Branch::query()->orderBy('name')->get() as $branch) {
            $branchStockInputs[] = TextInput::make("branch_stock.{$branch->id}")
                ->label($branch->name)
                ->numeric()
                ->minValue(0)
                ->default(fn (?Product $record) =>
                    $record?->branchStocks->firstWhere('branch_id', $branch->id)?->stock ?? 0
                )
                ->afterStateHydrated(function (TextInput $component, $state, ?Product $record) use ($branch) {
                    if ($record) {
                        $existing = $record->branchStocks->firstWhere('branch_id', $branch->id)?->stock ?? 0;
                        $component->state($existing);
                    }
                });
        }

        return $form->schema([
            Section::make('Temel Bilgiler')->schema([
                TextInput::make('sku')->label('SKU')->required()->unique(ignoreRecord: true),
                TextInput::make('name')->label('Ad')->required(),
                TextInput::make('price')->label('Fiyat (TRY)')->numeric()->required()->default(0),
                TextInput::make('sale_price')->label('İndirimli Fiyat (TRY)')->numeric()->nullable()
                    ->helperText('Boş bırakın veya 0 girin (indirim yok).'),
                TextInput::make('image')->label('Görsel URL')->nullable(),
            ])->columns(2),

            Section::make('Şube Stokları')->schema([
                Grid::make(3)->schema($branchStockInputs),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        // One column per branch; no global stock column
        $branchColumns = [];
        foreach (Branch::query()->orderBy('name')->get() as $branch) {
            $branchColumns[] = Tables\Columns\TextColumn::make("branch_{$branch->slug}")
                ->label($branch->name)
                ->getStateUsing(function (Product $record) use ($branch) {
                    return $record->branchStocks->firstWhere('branch_id', $branch->id)->stock ?? 0;
                })
                ->sortable(query: function (Builder $query, string $direction) use ($branch) {
                    $sub = ProductBranchStock::select('stock')
                        ->whereColumn('product_branch_stock.product_id', 'products.id')
                        ->where('product_branch_stock.branch_id', $branch->id)
                        ->limit(1);

                    $query->orderByRaw('COALESCE((' . $sub->toSql() . '), 0) ' . $direction, $sub->getBindings());
                });
        }

        return $table
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ad')->searchable()->limit(60),
                Tables\Columns\ImageColumn::make('image')->label('Görsel')->size(48)->square(),
                Tables\Columns\TextColumn::make('price')->label('Fiyat')->money('try', true)->sortable(),
                Tables\Columns\TextColumn::make('sale_price')->label('İndirimli')->money('try', true)->sortable()->color('danger'),
            ], $branchColumns))
            ->actions([Tables\Actions\EditAction::make()->label('Düzenle')])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()->label('Toplu Sil')])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
