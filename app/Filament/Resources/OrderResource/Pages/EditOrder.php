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

    public array $originalItems    = [];
    public ?int  $originalBranchId = null;

    public function mount($record): void
    {
        parent::mount($record);

        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }

        $this->originalBranchId = (int) $this->record->branch_id;
        $this->originalItems    = $this->itemsArrayFromDb();
    }

    protected function getFormActions(): array
    {
        // Non-depo users only get the Cancel/Back button for 'depo' status orders
        if (($this->record->status ?? null) === 'depo' && ! auth()->user()?->hasRole('depo')) {
            return [$this->getCancelFormAction()];
        }

        return parent::getFormActions();
    }

    protected function beforeSave(): void
    {
        if (($this->record->status ?? null) === 'tamamlandi') {
            abort(403, 'Tamamlanmış siparişler düzenlenemez.');
        }

        if (($this->record->status ?? null) === 'depo' && ! auth()->user()?->hasRole('depo')) {
            abort(403, 'Depo durumundaki siparişleri sadece depo yetkilisi düzenleyebilir.');
        }
    }

    protected function itemsArrayFromDb(): array
    {
        return $this->record->items()
            ->get(['product_id', 'qty'])
            ->map(fn ($r) => ['product_id' => (int) $r->product_id, 'qty' => (int) $r->qty])
            ->values()->all();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $rawState = $this->form->getRawState();

        if (array_key_exists('customer_id', $rawState)) {
            $data['customer_id'] = (int) $rawState['customer_id'];
        }

        $data['items'] = $rawState['items'] ?? [];
        $data          = \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($data);

        unset($data['items']);
        return $data;
    }

    /**
     * After the form is filled (on edit page load), recompute totals and
     * also prime the stock cache so all N rows load with ONE batch query.
     */
    protected function afterFill(): void
    {
        $state = $this->form->getRawState();

        // FIX: Batch-load stocks for all existing items on page load
        // so the form renders without N individual DB queries
        $branchId = (int) ($state['branch_id'] ?? 0);
        if ($branchId && ! empty($state['items'])) {
            $productIds = array_values(array_filter(array_column($state['items'], 'product_id')));

            if (! empty($productIds)) {
                $stocks = ProductBranchStock::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('product_id', $productIds)
                    ->pluck('stock', 'product_id');

                // Prime the cache
                foreach ($productIds as $pid) {
                    $pid = (int) $pid;
                    OrderResource::primeStockCache($pid, $branchId, (int) ($stocks[$pid] ?? 0));
                }

                // Write fresh stock_snapshot into each item in state
                foreach ($state['items'] as $i => $row) {
                    $pid = (int) ($row['product_id'] ?? 0);
                    if (! $pid) continue;
                    $state['items'][$i]['stock_snapshot'] = (int) ($stocks[$pid] ?? 0);
                }
            }
        }

        $state = \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($state);
        $this->form->fill($state);
    }

    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items']);

        // Sync Vergi Numarası back to User Profile
        if ($order->customer_id && ! empty($order->billing_tax_number)) {
            \App\Models\User::where('id', $order->customer_id)
                ->update(['tax_number' => $order->billing_tax_number]);
        }

        $newBranchId = (int) $order->branch_id;
        $oldBranchId = (int) $this->originalBranchId;

        $oldItems = $this->originalItems;
        $newItems = $this->itemsArrayFromDb();

        $remaining = BranchStockService::applyForCreateOrEdit(
            oldBranchId: $oldBranchId,
            newBranchId: $newBranchId,
            oldItems:    $oldItems,
            newItems:    $newItems
        );

        DB::transaction(function () use ($order, $remaining) {
            foreach ($order->items as $item) {
                $pid  = (int) $item->product_id;
                $left = $remaining[$pid] ?? ProductBranchStock::query()
                    ->where('branch_id', $order->branch_id)
                    ->where('product_id', $pid)
                    ->value('stock');
                $item->stock_snapshot = (int) ($left ?? 0);
                $item->saveQuietly();
            }
        });

        $this->originalBranchId = (int) $order->branch_id;
        $this->originalItems    = $this->itemsArrayFromDb();

        \Filament\Notifications\Notification::make()
            ->title('Sipariş güncellendi')
            ->body("#{$order->id} numaralı sipariş başarıyla kaydedildi.")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('pdf')
                ->label('PDF İndir')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('orders.pdf', ['order' => $this->record, 't' => time()]), shouldOpenInNewTab: true),
        ];
    }
}
