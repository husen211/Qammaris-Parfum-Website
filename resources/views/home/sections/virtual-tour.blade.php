{{-- TOUR-01: the same walkable store tour as on the store location page. --}}
<section class="pt-24 bg-white" aria-labelledby="virtual-tour-title">
    <div class="container mx-auto px-6" data-reveal>
        <x-virtual-tour />
        <p class="mt-4 text-sm text-brand-black/60">
            Ingin datang langsung? Lihat <a href="{{ route('store.location') }}" class="text-brand-black underline underline-offset-4 hover:text-brand-gold">alamat dan jam buka toko</a>.
        </p>
    </div>
</section>
