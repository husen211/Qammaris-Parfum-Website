@extends('layouts.app')
@section('title', 'Tes Preferensi Parfum — Qammaris')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="bg-brand-cream pt-28 pb-10 md:pt-32 md:pb-16">
 <div class="mx-auto max-w-3xl px-5">
  <p class="text-sm text-brand-black/60">Tes preferensi · versi uji</p>
  <h1 class="mt-3 font-mayluxa text-4xl md:text-5xl">Cari parfum sesuai seleramu</h1>
  <p class="mt-4 text-brand-black/70">Untuk diri sendiri. Jawab tentang selera dan pemakaianmu; pertanyaan menyesuaikan jawabanmu. Dua pilihan terakhir boleh dilewati.</p>
  <form data-preference-form method="post" action="{{ route('quiz.store') }}" class="mt-8 bg-white border border-brand-black/10 p-5 md:p-8" novalidate>
   @csrf
   <p data-step-label class="text-sm text-brand-black/60" aria-live="polite">Pertanyaan 1 dari {{ count($questions) }}</p>
   <progress data-progress class="mt-3 w-full" max="{{ count($questions) + 1 }}" value="1" aria-label="Progres tes"></progress>
   @foreach($questions as $key => $question)
   <fieldset data-preference-step data-key="{{ $key }}" data-type="{{ $question['type'] }}" data-optional="{{ !empty($question['optional']) ? 'true' : 'false' }}" class="mt-7" @if(!$loop->first) hidden @endif>
    <legend tabindex="-1" class="font-mayluxa text-2xl md:text-3xl">{{ $question['label'] }}</legend>
    <p class="mt-2 text-sm text-brand-black/60">{{ $question['helper'] }}</p>
    @if($question['type'] === 'budget')
      <div class="mt-5 grid grid-cols-2 gap-3">
       @foreach([[0, 100000, 'Sampai Rp100 ribu'], [100000, 200000, 'Rp100–200 ribu'], [200000, 300000, 'Rp200–300 ribu'], [300000, 500000, 'Rp300–500 ribu'], [500000, 1000000, 'Rp500 ribu–1 juta'], [1000000, null, 'Rp1 juta ke atas']] as [$minimum, $maximum, $label])
       <button type="button" data-budget="{{ $maximum }}" data-min="{{ $minimum }}" aria-pressed="false" class="pref-option">{{ $label }}</button>
       @endforeach
      </div>
      <button type="button" data-budget-custom class="pref-link mt-4 min-h-11">Isi angka sendiri</button>
      <label for="pref-budget" class="mt-3 block text-sm">Atau isi budget maksimal (rupiah)</label>
      <input id="pref-budget" name="budget_max" type="number" inputmode="numeric" min="1" max="99999999" step="1" class="mt-2 w-full border border-brand-black/30 p-3 pref-budget-input" aria-describedby="pref-budget-status" placeholder="Contoh: 350000">
      <label class="mt-4 flex min-h-11 items-center gap-3"><input type="checkbox" data-budget-free> Budget tidak dibatasi</label>
      <p id="pref-budget-status" data-budget-status class="mt-2 text-sm text-brand-black/60" aria-live="polite">Kamu bisa mengisi batas maksimal sendiri.</p>
    @elseif($question['type'] === 'favorite')
      <label for="pref-favorite-search" class="mt-5 block text-sm">Cari nama parfum</label>
      <input id="pref-favorite-search" type="search" data-favorite-search class="mt-2 w-full border border-brand-black/30 p-3" placeholder="Ketik nama parfum" autocomplete="off">
      <p data-favorite-selected class="mt-3 text-sm" aria-live="polite">Belum ada parfum dipilih.</p>
      <div data-favorite-options class="mt-3 max-h-64 overflow-y-auto" role="group" aria-label="Hasil pencarian parfum"></div>
      <button type="button" data-favorite-clear class="pref-link mt-3">Lewati / hapus pilihan</button>
      <input type="hidden" name="favorite_product_id">
    @else
      <div class="mt-5 grid gap-3 sm:grid-cols-2">
       @if($key === 'avoid')
       <label class="pref-option flex items-center gap-3"><input type="checkbox" data-avoid-sweet class="h-4 w-4 shrink-0"> <span>Aroma manis</span></label>
       @endif
       @foreach($question['options'] as $value => $label)
       <label class="pref-option flex items-center gap-3">
        <input type="{{ $question['type'] === 'multi' ? 'checkbox' : 'radio' }}" name="{{ $key }}" value="{{ $value }}" class="h-4 w-4 shrink-0"> <span>{{ $label }}</span>
       </label>
       @endforeach
       @if($question['type'] === 'multi')
       <label class="pref-option flex items-center gap-3"><input type="checkbox" data-none="{{ $key }}" class="h-4 w-4"> <span>{{ $key === 'likes' ? 'Belum tahu' : 'Tidak ada / belum tahu' }}</span></label>
       @endif
      </div>
    @endif
   </fieldset>
   @endforeach
   <section data-preference-summary class="mt-7" hidden>
     <h2 class="font-mayluxa text-3xl" tabindex="-1">Cek jawabanmu</h2>
     <div data-summary-rows class="mt-5 divide-y divide-brand-black/10"></div>
     <p data-branch-summary class="mt-4 text-sm text-brand-black/70" hidden></p>
     <p class="mt-5 text-sm text-brand-black/60">Jawaban dan feedback disimpan anonim tanpa penghapusan otomatis untuk evaluasi rekomendasi. Kami tidak meminta nama atau nomor HP. Hasil dapat dibuka selama tujuh hari di browser ini.</p>
   </section>
   <p data-preference-error role="alert" class="mt-5 text-sm text-red-800" hidden></p>
   <div class="mt-7 flex flex-wrap items-center justify-between gap-3">
     <button type="button" data-preference-back class="pref-link min-h-12 px-2" hidden>Kembali</button>
     <button type="button" data-preference-next class="pref-button ml-auto">Lanjut</button>
     <button type="submit" data-preference-submit class="pref-button ml-auto" hidden>Lihat rekomendasi</button>
   </div>
   <p class="mt-4 text-xs text-brand-black/60" data-draft-status aria-live="polite">Jawaban sementara tersimpan di browser selama 24 jam.</p>
   <noscript><p class="mt-5">Aktifkan JavaScript untuk menjalankan tes bertahap ini.</p></noscript>
  </form>
  <p class="mt-5 text-sm text-brand-black/60">Rekomendasi masih ditinjau. Coba tester di toko sebelum menentukan pilihan; hasil tes belum membuktikan kamu akan menyukai aromanya.</p>
 </div>
</section>
<script type="application/json" id="preference-seed">{!! json_encode(['answers' => $answers, 'favorites' => $favorites, 'questionVersion' => config('fragrance_preference.question_version')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
