<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // keep "Create"
            $this->getCancelFormAction(), // keep "Cancel"
            // $this->getCreateAnotherFormAction(), // removed
        ];
    }

    /**
     * Before record is created, set creator, recompute totals,
     * and prefill shipping from customer
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // IMPORTANT: use RAW state so 'items' is still present on create
        $state = $this->form->getRawState();             // ← key change
        if (empty($state)) {
            // fallback: Filament posts under "data"
            $state = request()->input('data', []);
        }

        $items = $state['items'] ?? [];

        $subtotal = 0.0;
        foreach ($items as $row) {
            $q = (float)($row['qty'] ?? 0);
            $p = (float)($row['unit_price'] ?? 0);
            $subtotal += $q * $p;
        }

        $shipping = (float)($state['shipping_amount'] ?? $data['shipping_amount'] ?? 0);
        $data['subtotal'] = round($subtotal, 2);
        $data['total']    = round($subtotal + $shipping, 2);

        $data['created_by_id'] = \Auth::id();

        // … your existing customer prefill (unchanged) …
        if (!empty($data['customer_id'])) {
            if ($c = \App\Models\User::find($data['customer_id'])) {
                $data['shipping_name']          = $data['shipping_name']          ?? ($c->name ?? null);
                $data['shipping_phone']         = $data['shipping_phone']         ?? ($c->phone ?? null);
                $data['shipping_address_line1'] = $data['shipping_address_line1'] ?? ($c->address_line1 ?? null);
                $data['shipping_address_line2'] = $data['shipping_address_line2'] ?? ($c->address_line2 ?? null);
                $data['shipping_city']          = $data['shipping_city']          ?? ($c->city ?? null);
                $data['shipping_state']         = $data['shipping_state']         ?? ($c->state ?? null);
                $data['shipping_postcode']      = $data['shipping_postcode']      ?? ($c->postcode ?? null);
                $data['shipping_country']       = $data['shipping_country']       ?? ($c->country ?? null);
            }
        }

        return $data;
    }



    /** After the record exists, render and store a PDF */
    protected function afterCreate(): void
    {
        $order = $this->record->fresh(['items.product', 'customer']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order]);

        $path = "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $order->update(['pdf_path' => $path]);
    }
    protected function getRedirectUrl(): string
    {
        // Go back to the Orders index after a successful create
        return $this->getResource()::getUrl('index');
    }
}
