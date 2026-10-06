@php($activeValue = (string) old('is_active', isset($brand) ? (int) $brand->is_active : 1))

@if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        <p class="font-semibold">Periksa kembali data brand.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label for="name" class="block text-sm font-semibold text-gray-800">Nama brand</label>
    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $brand->name ?? '') }}"
        class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10"
        placeholder="Contoh: Lattafa">
</div>

<div>
    <label for="description" class="block text-sm font-semibold text-gray-800">Deskripsi <span class="font-normal text-gray-400">(opsional)</span></label>
    <textarea id="description" name="description" rows="5" maxlength="2000"
        class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10"
        placeholder="Catatan singkat tentang brand">{{ old('description', $brand->description ?? '') }}</textarea>
</div>

<div>
    <label for="is_active" class="block text-sm font-semibold text-gray-800">Status</label>
    <select id="is_active" name="is_active" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10">
        <option value="1" @selected($activeValue === '1')>Aktif — tersedia untuk produk baru</option>
        <option value="0" @selected($activeValue === '0')>Nonaktif — relasi produk tetap disimpan</option>
    </select>
</div>

<div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
    <a href="{{ route('admin.brands.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</a>
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">{{ $submitLabel }}</button>
</div>
