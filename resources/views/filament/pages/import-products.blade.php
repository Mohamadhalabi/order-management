{{-- resources/views/filament/pages/import-products.blade.php --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" color="warning">
                İçe Aktar
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    <x-filament::section class="mt-6" heading="Beklenen sütunlar">
        <p class="text-sm text-gray-600">
            Başlıklar (büyük/küçük harfe duyarsız): <code>sku</code>, <code>stock</code>.
            Yalnızca <strong>stock</strong> güncellenir. <strong>SKU</strong> yoksa satır <em>atlanır</em>.
            Yeni ürün <strong>oluşturulmaz</strong> ve <em>name/price</em> gibi diğer sütunlar dikkate alınmaz.
        </p>
    </x-filament::section>

</x-filament-panels::page>
