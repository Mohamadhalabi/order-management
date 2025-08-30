<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Snapshot of the order's product totals when the page loaded.
     * Format: [product_id => qty_total]
     */
    public array $originalTotals = [];

    public function mount($record): void
    {
        parent::mount($record);

        // ❌ Block editing completed orders
        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }

        // Build the "before" snapshot from DB
        $this->originalTotals = $this->totalsFrom($this->record->items()->get());
    }

    /**
     * As a second line of defense, block save on completed records
     * (e.g., if status flips to tamamlandi in a concurrent process).
     */
    protected function beforeSave(): void
    {
        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }
    }

    /**
     * Create a [product_id => qty_total] map from a collection of items.
     */
    protected function totalsFrom($items): array
    {
        $out = [];
        foreach ($items as $row) {
            $pid = (int) ($row->product_id ?? 0);
            $qty = (float) ($row->qty ?? 0);
            if ($pid <= 0 || $qty == 0) {
                continue;
            }
            $out[$pid] = ($out[$pid] ?? 0) + $qty;
        }
        return $out;
    }

    /**
     * After Filament persists the repeater changes, adjust product stock
     * by the *difference* between new and original totals.
     */
    protected function afterSave(): void
    {
        // Recompute "after" snapshot from DB
        $currentTotals = $this->totalsFrom($this->record->items()->get());

        // Compute per-product deltas
        $productIds = array_unique(array_merge(
            array_keys($this->originalTotals),
            array_keys($currentTotals),
        ));

        DB::transaction(function () use ($productIds, $currentTotals) {
            foreach ($productIds as $pid) {
                if (!$pid) continue;

                $before = (float) ($this->originalTotals[$pid] ?? 0);
                $after  = (float) ($currentTotals[$pid]   ?? 0);
                $delta  = $after - $before;   // +ve => take from stock, -ve => return to stock

                if ($delta == 0.0) continue;

                $product = Product::lockForUpdate()->find($pid);
                if (!$product) continue;

                // Find the stock column your model actually uses.
                $stockColumn = collect(['stock', 'stock_quantity', 'quantity'])
                    ->first(fn ($c) => !is_null($product->getAttribute($c))) ?? 'stock';

                $currentStock = (float) $product->getAttribute($stockColumn);

                if ($delta > 0) {
                    // Ordered more than before => decrease stock
                    $product->setAttribute($stockColumn, max(0, $currentStock - $delta));
                } else {
                    // Ordered less than before => increase stock
                    $product->setAttribute($stockColumn, $currentStock + abs($delta));
                }

                $product->save();
            }
        });

        // Reset snapshot so subsequent saves with no changes do nothing
        $this->originalTotals = $this->totalsFrom($this->record->items()->get());
    }
}
