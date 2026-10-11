@extends('layouts.app')
@section('title', 'Tes Preferensi Parfum — Qammaris')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="bg-brand-cream pt-28 pb-10 md:pt-32 md:pb-16">
 <div class="mx-auto max-w-3xl px-4 sm:px-5">
 <div class="pref-intro">
  <p class="text-sm text-brand-black/60">Tes preferensi · versi uji</p>
  <h1 class="mt-3 font-mayluxa text-3xl md:text-4xl">Cari parfum sesuai seleramu</h1>
  <p class="mt-4 text-brand-black/70">Untuk diri sendiri. Jawab tentang selera dan pemakaianmu; pertanyaan menyesuaikan jawabanmu. Dua pilihan terakhir boleh dilewati.</p>
 </div>
  <form data-preference-form method="post" action="{{ route('quiz.store') }}" class="pref-wizard" novalidate>
   @csrf
   {{-- Adapted from 21st.dev Multistep Form, arihantcodes_1f7b8c4d / demo4883. --}}
   <nav class="pref-stages" aria-label="Tahap tes preferensi">
    @foreach(['Budget', 'Pemakaian', 'Aroma', 'Karakter', 'Pilihan', 'Ringkasan'] as $stage)
    <button type="button" class="pref-stage" data-preference-stage="{{ $loop->index }}" data-state="{{ $loop->first ? 'current' : 'pending' }}" aria-label="{{ $stage }}" @if($loop->first) aria-current="step" @else disabled @endif>
     <span class="pref-stage-dot" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg></span>
     <span class="pref-stage-label">{{ $stage }}</span>
    </button>
    @endforeach
   </nav>
   <div class="pref-track" aria-hidden="true"><div class="pref-track-fill" data-progress-fill></div></div>
   <progress data-progress class="sr-only" max="{{ count($questions) }}" value="0" aria-label="Progres tes"></progress>
   <div class="pref-step-caption"><strong data-stage-label>Budget</strong><p data-step-label aria-live="polite">Pertanyaan 1 dari {{ count($questions) }}</p></div>
   <div class="pref-card">
   <div class="pref-stage-frame" data-step-frame>
   @foreach($questions as $key => $question)
   <fieldset data-preference-step data-key="{{ $key }}" data-type="{{ $question['type'] }}" data-optional="{{ !empty($question['optional']) ? 'true' : 'false' }}" class="pref-panel" @if(!$loop->first) hidden @endif>
    <legend tabindex="-1" class="font-mayluxa text-2xl md:text-3xl">{{ $question['label'] }}</legend>
    <p class="mt-2 text-sm text-brand-black/60">{{ $question['helper'] }}</p>
    @if($question['type'] === 'budget')
      <div class="pref-options pref-budget-options">
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
      <div class="pref-options {{ $question['type'] === 'multi' ? 'pref-aroma-options' : '' }}">
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
   <section data-preference-summary class="pref-panel" hidden inert>
     <h2 class="font-mayluxa text-3xl" tabindex="-1">Cek jawabanmu</h2>
     <div data-summary-rows class="mt-5 divide-y divide-brand-black/10"></div>
     <p data-branch-summary class="mt-4 text-sm text-brand-black/70" hidden></p>
     <p class="mt-5 text-sm text-brand-black/60">Jawaban dan feedback disimpan anonim tanpa penghapusan otomatis untuk evaluasi rekomendasi. Kami tidak meminta nama atau nomor HP. Hasil dapat dibuka selama tujuh hari di browser ini.</p>
   </section>
   </div>
   <p data-preference-error role="alert" class="mt-5 text-sm text-red-800" hidden></p>
   <div class="pref-controls">
     <button type="button" data-preference-back class="pref-back" hidden><x-icon name="arrow-left" /><span>Kembali</span></button>
     <button type="button" data-preference-next class="pref-button"><span data-next-label>Lanjut</span><x-icon name="arrow-right" /></button>
     <button type="submit" data-preference-submit class="pref-button" hidden><span data-submit-label>Lihat rekomendasi</span><x-icon name="arrow-right" data-submit-arrow /><svg data-submit-spinner aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2a10 10 0 1 1-7.07 2.93" /></svg></button>
   </div>
   <p class="pref-draft-note" data-draft-status aria-live="polite">Jawaban sementara tersimpan di browser selama 24 jam.</p>
   <noscript><p class="mt-5">Aktifkan JavaScript untuk menjalankan tes bertahap ini.</p></noscript>
   </div>
  </form>
  <p class="mt-5 text-sm text-brand-black/60">Rekomendasi masih ditinjau. Coba tester di toko sebelum menentukan pilihan; hasil tes belum membuktikan kamu akan menyukai aromanya.</p>
 </div>
</section>
<script type="application/json" id="preference-seed">{!! json_encode(['answers' => $answers, 'favorites' => $favorites, 'questionVersion' => config('fragrance_preference.question_version')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
