<x-filament::page>
    <form wire:submit.prevent="submit" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Import
        </x-filament::button>

        <x-filament::section>
            <x-slot name="heading">Expected columns</x-slot>
            <div>
                Headers (case-insensitive): <strong>sku</strong>, <strong>stock</strong>.
                Only the <em>selected branch</em> stock is updated. Rows without SKU are skipped.
                If SKU doesn’t start with <code>AA</code>, it’s automatically prefixed.
            </div>
        </x-filament::section>
    </form>
</x-filament::page>
