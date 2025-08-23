<x-filament::page>
    {{ $this->form }}
    <x-filament::button class="mt-4" wire:click="submit">
        Import
    </x-filament::button>

    <x-filament::section class="mt-6" heading="Expected columns">
        <div class="text-sm text-gray-500">
            Headers (case‑insensitive): <code>sku</code>, <code>name</code>, <code>stock</code>, <code>price</code>.
            Existing SKU → stock (and optionally name/price) updated. Missing SKU → product created.
        </div>
    </x-filament::section>
</x-filament::page>
