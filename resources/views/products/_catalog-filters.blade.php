@php
    $autosubmit = $filterSurface === 'desktop';
@endphp

<div class="catalog-filter-field">
    <span id="{{ $filterSurface }}-brand-label" class="catalog-filter-label">Brand</span>
    <details class="catalog-filter-dropdown" data-catalog-dropdown name="{{ $filterSurface }}-filters">
        <summary aria-labelledby="{{ $filterSurface }}-brand-label {{ $filterSurface }}-brand-value">
            <span id="{{ $filterSurface }}-brand-value" data-catalog-brand-summary>{{ count($selectedBrands) ? count($selectedBrands).' brand dipilih' : 'Semua brand' }}</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-width="1.5" /></svg>
        </summary>
        <div class="catalog-filter-dropdown__body">
            <label for="{{ $filterSurface }}-brand-search" class="sr-only">Cari dalam daftar brand</label>
            <input id="{{ $filterSurface }}-brand-search" type="search" data-catalog-brand-search maxlength="100" placeholder="Cari brand" class="catalog-filter-control mb-2" autocomplete="off">
            <div data-catalog-brand-list class="catalog-brand-options">
                @forelse ($brands as $brand)
                    <label class="catalog-brand-option" data-catalog-brand-option>
                        <input type="checkbox" name="brand[]" value="{{ $brand->id }}" {{ in_array($brand->id, $selectedBrands, true) ? 'checked' : '' }} class="checkbox checkbox-sm rounded-none shrink-0 border-gray-300 checked:bg-brand-black checked:border-brand-black">
                        <span>{{ $brand->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500 py-3">Belum ada brand.</p>
                @endforelse
            </div>
            <p data-catalog-brand-empty hidden class="py-3 text-sm text-gray-500" role="status">Brand tidak ditemukan.</p>
            @if ($autosubmit)
                <button type="submit" class="catalog-filter-apply mt-3">Terapkan brand</button>
            @endif
        </div>
    </details>
</div>

<div class="catalog-filter-field">
    <label for="{{ $filterSurface }}-catalog-category" class="catalog-filter-label">Kategori</label>
    <select id="{{ $filterSurface }}-catalog-category" name="category" @if($autosubmit) data-catalog-autosubmit @endif class="catalog-filter-control">
        <option value="">Semua kategori</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" {{ $catalogState->categoryId === $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
        @endforeach
    </select>
</div>

<div class="catalog-filter-field">
    <label for="{{ $filterSurface }}-catalog-gender" class="catalog-filter-label">Peruntukan</label>
    <select id="{{ $filterSurface }}-catalog-gender" name="gender" @if($autosubmit) data-catalog-autosubmit @endif class="catalog-filter-control">
        <option value="">Semua peruntukan</option>
        @foreach (['Unisex', 'Pria', 'Wanita'] as $gender)
            <option value="{{ $gender }}" {{ $catalogState->gender === $gender ? 'selected' : '' }}>{{ $gender }}</option>
        @endforeach
    </select>
</div>

<div class="catalog-filter-field">
    <label for="{{ $filterSurface }}-catalog-availability" class="catalog-filter-label">Ketersediaan</label>
    <select id="{{ $filterSurface }}-catalog-availability" name="availability" @if($autosubmit) data-catalog-autosubmit @endif class="catalog-filter-control">
        <option value="">Semua status</option>
        <option value="available" {{ $catalogState->availability === 'available' ? 'selected' : '' }}>Tersedia</option>
        @if ($catalogState->availability === 'unknown')
            <option value="unknown" selected hidden>Belum ada info stok</option>
        @endif
        <option value="sold_out" {{ $catalogState->availability === 'sold_out' ? 'selected' : '' }}>Habis</option>
    </select>
</div>

<div class="catalog-filter-field">
    <span id="{{ $filterSurface }}-price-label" class="catalog-filter-label">Rentang harga</span>
    <details class="catalog-filter-dropdown" data-catalog-dropdown name="{{ $filterSurface }}-filters">
        <summary aria-labelledby="{{ $filterSurface }}-price-label {{ $filterSurface }}-price-value">
            <span id="{{ $filterSurface }}-price-value">
                @if ($catalogState->priceMin !== null || $catalogState->priceMax !== null)
                    {{ $catalogState->priceMin !== null ? format_rupiah($catalogState->priceMin) : format_rupiah(0) }} – {{ $catalogState->priceMax !== null ? format_rupiah($catalogState->priceMax) : 'ke atas' }}
                @else
                    Semua harga
                @endif
            </span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-width="1.5" /></svg>
        </summary>
        <div class="catalog-filter-dropdown__body">
            {{-- UX-02: stacked fields with room for Rp 1.000.000; JS adds thousands separators, the server accepts both forms. --}}
            <div class="catalog-price-range">
                @foreach (['min' => ['Harga minimum', $catalogState->priceMin, '0'], 'max' => ['Harga maksimum', $catalogState->priceMax, 'Tanpa batas']] as $bound => [$label, $amount, $placeholder])
                    <div>
                        <label class="catalog-price-range__label" for="{{ $filterSurface }}-price-{{ $bound }}">{{ $label }}</label>
                        <div class="catalog-price-range__input">
                            <span aria-hidden="true">Rp</span>
                            <input id="{{ $filterSurface }}-price-{{ $bound }}" type="text" inputmode="numeric" autocomplete="off" maxlength="11" name="price_{{ $bound }}"
                                value="{{ $amount !== null ? number_format($amount, 0, ',', '.') : '' }}" placeholder="{{ $placeholder }}" data-price-input class="catalog-filter-control">
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($autosubmit)
                <button type="submit" class="catalog-filter-apply mt-5">Terapkan harga</button>
            @endif
        </div>
    </details>
</div>
