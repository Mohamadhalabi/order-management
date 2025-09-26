<?php

namespace App\Services;

use App\Models\ProductBranchStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchStockService
{
    /**
     * Compute per-product deltas between old items and new items for a single branch.
     * Items arrays must be arrays of rows containing product_id, qty
     *
     * Returns: [product_id => deltaQty]  (positive => take from stock, negative => return to stock)
     */
    public static function computeItemDeltas(array $oldItems, array $newItems): array
    {
        $fold = [];
        foreach ($oldItems as $r) {
            $pid = (int) ($r['product_id'] ?? 0);
            $q   = (int) ($r['qty'] ?? 0);
            if ($pid) $fold[$pid] = ($fold[$pid] ?? 0) + $q;
        }

        $fnew = [];
        foreach ($newItems as $r) {
            $pid = (int) ($r['product_id'] ?? 0);
            $q   = (int) ($r['qty'] ?? 0);
            if ($pid) $fnew[$pid] = ($fnew[$pid] ?? 0) + $q;
        }

        $allPids = array_unique(array_merge(array_keys($fold), array_keys($fnew)));
        $delta   = [];
        foreach ($allPids as $pid) {
            $delta[$pid] = ($fnew[$pid] ?? 0) - ($fold[$pid] ?? 0);
        }

        return array_filter($delta, fn ($d) => (int) $d !== 0);
    }

    /**
     * Apply stock deltas on a given branch.
     * $deltaByProductId: [product_id => deltaQty]
     *   delta > 0 => order wants MORE than before => DECREASE branch stock by delta
     *   delta < 0 => order wants LESS than before => INCREASE branch stock by |delta|
     */
    public static function applyDeltasForBranch(int $branchId, array $deltaByProductId): void
    {
        if ($branchId <= 0 || empty($deltaByProductId)) return;

        foreach ($deltaByProductId as $productId => $deltaQty) {
            if (!$productId || $deltaQty == 0) continue;

            $pbs = ProductBranchStock::query()
                ->where('branch_id', $branchId)
                ->where('product_id', (int) $productId)
                ->lockForUpdate()
                ->first();

            if (!$pbs) {
                $pbs = new ProductBranchStock();
                $pbs->branch_id  = $branchId;
                $pbs->product_id = (int) $productId;
                $pbs->stock      = 0;
            }

            $newStock = (int) $pbs->stock - (int) $deltaQty; // delta>0 => decrease, delta<0 => increase

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'items' => "Yetersiz stok: Ürün #{$productId} (şube: {$branchId}). Değişiklik: {$deltaQty}, mevcut: {$pbs->stock}",
                ]);
            }

            $pbs->stock = $newStock;
            $pbs->save();
        }
    }

    /**
     * Main coordinator for create/edit:
     * - If branch changed: return all old to old branch, take all new from new branch.
     * - Else: apply delta = new - old on the same branch.
     *
     * Returns the latest per-product remaining stock for the affected branch (to fill stock_snapshot).
     */
    public static function applyForCreateOrEdit(
        ?int $oldBranchId,
        int $newBranchId,
        array $oldItems,
        array $newItems
    ): array {
        $remaining = [];

        DB::transaction(function () use ($oldBranchId, $newBranchId, $oldItems, $newItems, &$remaining) {
            if ($oldBranchId && $oldBranchId !== $newBranchId) {
                // Return all old to old branch (delta negative => increases stock)
                $returnToOld = [];
                foreach ($oldItems as $r) {
                    $pid = (int) ($r['product_id'] ?? 0);
                    $qty = (int) ($r['qty'] ?? 0);
                    if ($pid && $qty > 0) $returnToOld[$pid] = ($returnToOld[$pid] ?? 0) - $qty;
                }
                self::applyDeltasForBranch($oldBranchId, $returnToOld);

                // Take all new from new branch (delta positive => decreases stock)
                $takeFromNew = [];
                foreach ($newItems as $r) {
                    $pid = (int) ($r['product_id'] ?? 0);
                    $qty = (int) ($r['qty'] ?? 0);
                    if ($pid && $qty > 0) $takeFromNew[$pid] = ($takeFromNew[$pid] ?? 0) + $qty;
                }
                self::applyDeltasForBranch($newBranchId, $takeFromNew);

            } else {
                $delta = self::computeItemDeltas($oldItems, $newItems);
                if (!empty($delta)) {
                    self::applyDeltasForBranch($newBranchId, $delta);
                }
            }

            // Build remaining map for the new branch (used for stock_snapshot)
            $pids = array_unique(array_map(fn($r) => (int)($r['product_id'] ?? 0), $newItems));
            if (!empty($pids)) {
                $rows = ProductBranchStock::query()
                    ->where('branch_id', $newBranchId)
                    ->whereIn('product_id', $pids)
                    ->get(['product_id', 'stock']);
                foreach ($rows as $row) {
                    $remaining[(int)$row->product_id] = (int)$row->stock;
                }
            }
        });

        return $remaining;
    }
}
