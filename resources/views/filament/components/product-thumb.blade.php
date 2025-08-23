@props(['url' => null, 'size' => 72, 'alt' => 'Product image'])

<div
    class="relative flex items-center justify-center rounded-xl border bg-gray-50/80 dark:bg-gray-900/40
           border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden"
    style="width: {{ $size }}px; height: {{ $size }}px;"
>
    @if ($url)
        <img
            src="{{ $url }}"
            alt="{{ $alt }}"
            loading="lazy"
            decoding="async"
            referrerpolicy="no-referrer"
            class="max-w-full max-h-full object-contain"
            onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('hidden');"
        >
        <div class="hidden absolute inset-0 flex items-center justify-center text-xs text-gray-400 dark:text-gray-500">
            <div class="flex flex-col items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 016 4.5h12a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0118 19.5H6a2.25 2.25 0 01-2.25-2.25V6.75z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 15.75l4.5-4.5 3.75 3.75 2.25-2.25 6 6" />
                </svg>
                <span>No image</span>
            </div>
        </div>
    @else
        <div class="absolute inset-0 flex items-center justify-center text-xs text-gray-400 dark:text-gray-500">
            <div class="flex flex-col items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 016 4.5h12a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0118 19.5H6a2.25 2.25 0 01-2.25-2.25V6.75z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 15.75l4.5-4.5 3.75 3.75 2.25-2.25 6 6" />
                </svg>
                <span>No image</span>
            </div>
        </div>
    @endif

    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/30 to-transparent dark:from-white/5"></div>
</div>
