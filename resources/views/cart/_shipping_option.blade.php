{{-- ORD-13: one priced courier option. Also used as the template the checkout script fills (text only). --}}
<label class="flex min-h-16 cursor-pointer items-center gap-3 border border-gray-300 bg-white p-4 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] active:bg-gray-50 has-[:checked]:border-brand-black has-[:checked]:bg-[#FAF8F3] has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-black" data-option>
    <input type="radio" name="shipping_option" value="{{ $option['id'] ?? '' }}" @checked($checked) class="h-5 w-5 shrink-0 accent-brand-black" data-shipping-choice data-charged="{{ $option['charged'] ?? '' }}" data-price="{{ $option['price'] ?? '' }}">
    <span class="min-w-0 flex-1">
        <span class="block text-base font-medium text-brand-black" data-option-label>{{ $option['label'] ?? '' }}</span>
        <span class="block text-sm text-gray-600" data-option-eta>{{ $option['eta'] ?? '' }}</span>
        <span class="mt-1 block text-xs font-semibold text-[#8A6A2E]" data-option-discount @if (empty($option['discount'])) hidden @endif>{{ ! empty($option['discount']) ? 'Potongan ongkir '.$option['percent'].'% dari Qammaris' : '' }}</span>
    </span>
    <span class="shrink-0 text-right tabular-nums">
        <s class="block text-xs text-gray-400" data-option-price @if (empty($option['discount'])) hidden @endif>{{ isset($option['price']) ? format_rupiah($option['price']) : '' }}</s>
        <span class="block text-base font-semibold text-brand-black" data-option-charged>{{ isset($option['charged']) ? format_rupiah($option['charged']) : '' }}</span>
    </span>
</label>
