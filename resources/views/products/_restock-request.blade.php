{{-- WAIT-01: sold-out product → Qammaris App Daftar Tunggu. --}}
@php
    $restockErrors = $errors->getBag('restock');
    $restockStatus = session('restock_status');
    $fieldClass = 'mt-1.5 block min-h-12 w-full border border-gray-300 bg-white px-3 text-base text-brand-black placeholder:text-gray-400 focus:border-brand-black focus:outline-none focus:ring-1 focus:ring-brand-black aria-[invalid=true]:border-red-700';
@endphp

<div id="kabari" class="scroll-mt-28">
    @if ($restockStatus === 'saved')
        <div class="border border-gray-200 px-4 py-4" role="status">
            <p class="text-sm font-semibold text-brand-black">Permintaan tercatat</p>
            <p class="mt-1 text-xs leading-5 text-gray-600">Kami kabari lewat WhatsApp saat {{ $product->name }} tersedia lagi.</p>
        </div>
    @else
        @if ($restockStatus === 'available')
            <p class="mb-3 text-xs leading-5 text-gray-600" role="status">Produk ini sudah tersedia lagi. Muat ulang halaman untuk memesan.</p>
        @endif
        <details class="group" data-restock-request @if ($restockErrors->any()) open @endif>
            <summary class="flex min-h-14 w-full cursor-pointer list-none items-center justify-center bg-brand-black px-5 text-xs font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black group-open:hidden [&::-webkit-details-marker]:hidden">
                Kabari saya jika sudah ready
            </summary>

            <form method="POST" action="{{ route('products.restock-request', $product->slug) }}" class="border border-gray-200 px-4 py-4" data-restock-form novalidate>
                @csrf
                <p class="text-sm font-semibold text-brand-black">Kabari saya jika sudah ready</p>
                <p class="mt-1 text-xs leading-5 text-gray-500">Tinggalkan nama dan nomor WhatsApp. Kami kabari saat produk ini tersedia lagi.</p>

                <div class="mt-4">
                    <label for="restock-name" class="block text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500">Nama</label>
                    <input id="restock-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="60" required
                        @if ($restockErrors->has('name')) aria-invalid="true" aria-describedby="restock-name-error" @endif class="{{ $fieldClass }}">
                    @if ($restockErrors->has('name'))
                        <p id="restock-name-error" class="mt-1 text-xs text-red-700">{{ $restockErrors->first('name') }}</p>
                    @endif
                </div>

                <div class="mt-3">
                    <label for="restock-whatsapp" class="block text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500">No. WhatsApp</label>
                    <input id="restock-whatsapp" name="whatsapp" type="tel" inputmode="tel" value="{{ old('whatsapp') }}" autocomplete="tel" maxlength="30" placeholder="0812 3456 7890" required
                        aria-describedby="restock-whatsapp-hint{{ $restockErrors->has('whatsapp') ? ' restock-whatsapp-error' : '' }}"
                        @if ($restockErrors->has('whatsapp')) aria-invalid="true" @endif class="{{ $fieldClass }}">
                    @if ($restockErrors->has('whatsapp'))
                        <p id="restock-whatsapp-error" class="mt-1 text-xs text-red-700">{{ $restockErrors->first('whatsapp') }}</p>
                    @endif
                    <p id="restock-whatsapp-hint" class="mt-1 text-xs text-gray-500">Nomor hanya dipakai untuk kabar produk ini.</p>
                </div>

                {{-- Left empty by people; bots that fill every field are dropped. --}}
                <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="restock-website">Website</label>
                    <input id="restock-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                @if ($restockErrors->has('website'))
                    <p class="mt-3 text-xs text-red-700" role="alert">{{ $restockErrors->first('website') }}</p>
                @endif

                <button type="submit" data-restock-submit
                    class="mt-4 flex min-h-12 w-full items-center justify-center bg-brand-black px-5 text-xs font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black disabled:cursor-wait disabled:bg-gray-500">
                    Kabari saya
                </button>
            </form>
        </details>
    @endif
</div>
