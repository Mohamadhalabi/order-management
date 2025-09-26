<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\BranchStockService;
use App\Models\ProductBranchStock;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /** Snapshot when the page loads */
    public array $originalItems = [];   // [['product_id'=>..,'qty'=>..], ...]
    public ?int $originalBranchId = null;

    public function mount($record): void
    {
        parent::mount($record);

        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }

        $this->originalBranchId = (int) $this->record->branch_id;
        $this->originalItems = $this->itemsArrayFromDb();
    }

    protected function beforeSave(): void
    {
        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }
    }

    /** Convert current DB items to simple arrays */
    protected function itemsArrayFromDb(): array
    {
        return $this->record->items()
            ->get(['product_id','qty'])
            ->map(fn($r) => ['product_id' => (int)$r->product_id, 'qty' => (int)$r->qty])
            ->values()->all();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // keep your totals logic centralized
        return OrderResource::recomputeTotalsFromArray($data);
    }

    /**
     * After Filament persists changes:
     *  - If branch unchanged: apply delta = new - old on that branch
     *  - If branch changed: return all old to original branch, take all new from new branch
     *  - Update stock_snapshot for each line to remaining stock in active branch
     */
    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items']);

        $newBranchId = (int) $order->branch_id;
        $oldBranchId = (int) $this->originalBranchId;

        $oldItems = $this->originalItems;
        $newItems = $this->itemsArrayFromDb();

        // Apply branch-level stock logic
        $remaining = BranchStockService::applyForCreateOrEdit(
            oldBranchId: $oldBranchId,
            newBranchId: $newBranchId,
            oldItems: $oldItems,
            newItems: $newItems
        );

        // Update each item's stock_snapshot to remaining stock in the (new) branch
        DB::transaction(function () use ($order, $remaining) {
            foreach ($order->items as $item) {
                $pid = (int) $item->product_id;
                $left = $remaining[$pid] ?? ProductBranchStock::query()
                    ->where('branch_id', $order->branch_id)
                    ->where('product_id', $pid)
                    ->value('stock');
                $item->stock_snapshot = (int) ($left ?? 0);
                $item->saveQuietly();
            }
        });

        // Reset snapshots so subsequent edits compute fresh deltas
        $this->originalBranchId = (int) $order->branch_id;
        $this->originalItems    = $this->itemsArrayFromDb();
    }
}
