<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\BranchStockService;
use App\Models\ProductBranchStock;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Sipariş Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')->label('Oluştur')->submit('create')->color('primary'),
            Actions\Action::make('cancel')->label('İptal')->url(static::getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $state   = $this->form->getRawState() ?: request()->input('data', []);
        $payload = array_merge($data, $state);

        $items = $payload['items'] ?? [];
        if (empty($items)) {
            \Filament\Notifications\Notification::make()
                ->title('Siparişe en az 1 ürün eklemelisiniz.')
                ->danger()->send();
            $this->addError('data.items', 'Siparişe en az 1 ürün eklemelisiniz.');
            $this->halt();
        }

        $payload = \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($payload);
        $payload['created_by_id'] = \Illuminate\Support\Facades\Auth::id();

        return $payload;
    }

    /**
     * After create:
     *  - Deduct branch stock for each line (by ordered qty)
     *  - Update each item's stock_snapshot to remaining stock in that branch
     *  - Generate PDF
     */
    protected function afterCreate(): void
    {
        $order = $this->record->fresh(['items', 'customer', 'creator']);

        // Convert items to simple arrays for the service
        $newItems = $order->items->map(fn ($i) => [
            'product_id' => (int) $i->product_id,
            'qty'        => (int) $i->qty,
        ])->values()->all();

        // Apply branch-level stock moves
        $remaining = BranchStockService::applyForCreateOrEdit(
            oldBranchId: null,
            newBranchId: (int) $order->branch_id,
            oldItems: [],
            newItems: $newItems
        );

        // Update stock_snapshot per item to remaining branch stock (optional but useful)
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

        // PDF
        $pdf = Pdf::loadView('pdf.order', ['order' => $order->fresh(['items.product', 'customer', 'creator'])]);
        $path = "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $order->updateQuietly(['pdf_path' => $path]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
