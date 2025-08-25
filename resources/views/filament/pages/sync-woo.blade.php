<x-filament::page>
    <div class="flex flex-col gap-4">
        <div class="flex gap-3">
            <x-filament::button wire:click="syncProducts" icon="heroicon-o-cube">
                Ürünleri Senkronize Et (kuyruk)
            </x-filament::button>

            <x-filament::button wire:click="syncUsers" icon="heroicon-o-users">
                Müşterileri Senkronize Et (kuyruk)
            </x-filament::button>
        </div>

        <x-filament::section class="mt-6" heading="Notlar">
            <ul class="list-disc ms-6 text-sm text-gray-600">
                <li>Stok <strong>Woo senkronizasyonu ile güncellenmez</strong>. Stok için Excel içe aktarmayı kullanın.</li>
                <li>Ürünler önce <code>SKU</code> ile, bulunamazsa <code>wc_id</code> ile eşleştirilir.</li>
                <li>Müşterilerin bir <strong>e-posta</strong> adresi olmalıdır.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament::page>
