{{-- CAT-SIZE-OPTIONS-01: the catalog's most used sizes, one click into "Ukuran (ml)". resources/js/ui/product-size-options.js --}}
@if($commonSizes !== [])
    <div data-size-options data-size-input="#offer-volume" class="sm:col-span-3" role="group" aria-labelledby="size-options-label">
        <p id="size-options-label" class="mb-1 text-xs text-gray-500">Ukuran yang sering dipakai</p>
        <div class="flex flex-wrap gap-2">
            @foreach($commonSizes as $size)
                <button type="button" data-size="{{ $size }}" aria-pressed="false"
                    class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm tabular-nums text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 aria-pressed:border-gray-900 aria-pressed:bg-gray-900 aria-pressed:text-white">{{ $size }} ml</button>
            @endforeach
        </div>
    </div>
@endif
