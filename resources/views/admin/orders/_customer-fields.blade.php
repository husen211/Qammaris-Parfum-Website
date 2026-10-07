@php
    $order ??= null;
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $value = fn (string $field) => old($field, $order?->{$field});
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="customer_name" class="block text-sm font-medium text-gray-700">Nama penerima</label>
        <input id="customer_name" name="customer_name" value="{{ $value('customer_name') }}" maxlength="100" class="{{ $input }}">
        @error('customer_name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="customer_phone" class="block text-sm font-medium text-gray-700">Nomor HP / WA</label>
        <input id="customer_phone" name="customer_phone" type="tel" inputmode="tel" value="{{ $value('customer_phone') }}" maxlength="25" class="{{ $input }}">
        @error('customer_phone')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="fulfillment" class="block text-sm font-medium text-gray-700">Cara menerima</label>
        <select id="fulfillment" name="fulfillment" class="{{ $input }}">
            <option value="">Pilih…</option>
            @foreach (\App\Models\OnlineOrder::FULFILLMENTS as $key => $label)
                <option value="{{ $key }}" @selected($value('fulfillment') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('fulfillment')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="packaging" class="block text-sm font-medium text-gray-700">Paperbag</label>
        <select id="packaging" name="packaging" class="{{ $input }}">
            <option value="">Pilih…</option>
            @foreach (\App\Models\OnlineOrder::PACKAGING as $key => $label)
                <option value="{{ $key }}" @selected($value('packaging') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('packaging')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="address" class="block text-sm font-medium text-gray-700">Alamat / patokan <span class="font-normal text-gray-500">(wajib lengkap untuk luar kota)</span></label>
        <textarea id="address" name="address" rows="2" maxlength="500" class="{{ $input }}">{{ $value('address') }}</textarea>
        @error('address')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="postcode" class="block text-sm font-medium text-gray-700">Kode pos <span class="font-normal text-gray-500">(luar kota)</span></label>
        <input id="postcode" name="postcode" value="{{ $value('postcode') }}" inputmode="numeric" maxlength="5" class="{{ $input }}">
        @error('postcode')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="customer_note" class="block text-sm font-medium text-gray-700">Catatan customer</label>
        <input id="customer_note" name="customer_note" value="{{ $value('customer_note') }}" maxlength="300" class="{{ $input }}">
        @error('customer_note')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
