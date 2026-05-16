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

    protected static ?string $title      = 'Sipariş Oluştur';
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
        $rawState = $this->form->getRawState();
        $items    = $rawState['items'] ?? [];

        if (empty($items)) {
            \Filament\Notifications\Notification::make()
                ->title('Siparişe en az 1 ürün eklemelisiniz.')
                ->danger()->send();
            $this->addError('data.items', 'Siparişe en az 1 ürün eklemelisiniz.');
            $this->halt();
        }

        if (isset($data['customer_id'])) {
            $data['customer_id'] = (int) $data['customer_id'];
        }

        $data['items'] = $items;
        $data          = \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($data);
        $data['created_by_id'] = \Illuminate\Support\Facades\Auth::id();

        unset($data['items']);
        return $data;
    }

    /**
     * After create:
     *  - Sync tax number back to the user
     *  - Deduct branch stock for each line
     *  - Update each item's stock_snapshot to remaining branch stock
     *  - Generate PDF
     */
    protected function afterCreate(): void
    {
        $order = $this->record->fresh(['items', 'customer', 'creator']);

        // Sync Vergi Numarası back to User Profile
        if ($order->customer_id && ! empty($order->billing_tax_number)) {
            \App\Models\User::where('id', $order->customer_id)
                ->update(['tax_number' => $order->billing_tax_number]);
        }

        $newItems = $order->items->map(fn ($i) => [
            'product_id' => (int) $i->product_id,
            'qty'        => (int) $i->qty,
        ])->values()->all();

        $remaining = BranchStockService::applyForCreateOrEdit(
            oldBranchId: null,
            newBranchId: (int) $order->branch_id,
            oldItems:    [],
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

        // Generate PDF
        $html = view('pdf.order', [
            'order' => $order->fresh(['items.product', 'customer', 'creator']),
            'brand' => $this->brandMeta(),
            'code'  => $order->currency_code ?: 'USD',
            'sym'   => \App\Models\Currency::symbolFor($order->currency_code ?: 'USD') ?: ($order->currency_code ?: 'USD'),
        ])->render();

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $path = "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $order->updateQuietly(['pdf_path' => $path]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    private function brandMeta(): array
    {
        return [
            'name'    => 'Anadolu Anahtar',
            'logo'    => public_path('images/Logo-Normal.webp'),
            'address' => 'Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin',
            'phone'   => '(+90) 552 436 80 30',
            'email'   => 'Satis@aanahtar.com.tr',
            'color'   => '#2D83B0',
        ];
    }
}
