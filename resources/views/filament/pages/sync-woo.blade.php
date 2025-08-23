<x-filament::page>
    <div class="flex flex-col gap-4">
        <div class="flex gap-3">
            <x-filament::button wire:click="syncProducts" icon="heroicon-o-cube">
                Sync Products (queue)
            </x-filament::button>
            <x-filament::button wire:click="syncUsers" icon="heroicon-o-users">
                Sync Users (queue)
            </x-filament::button>
        </div>

        <div class="flex gap-3">
            <x-filament::button color="warning" wire:click="debugProducts" icon="heroicon-o-bug-ant">
                Debug Products (run now)
            </x-filament::button>
            <x-filament::button color="warning" wire:click="debugUsers" icon="heroicon-o-bug-ant">
                Debug Users (run now)
            </x-filament::button>
        </div>

        <x-filament::section class="mt-6" heading="Notes">
            <ul class="list-disc ms-6 text-sm text-gray-600">
                <li>Stock is <strong>NOT</strong> updated by Woo sync. Use Excel Import for stock.</li>
                <li>Products matched by <code>SKU</code>, otherwise by <code>wc_id</code>.</li>
                <li>Customers require an email.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament::page>
