@extends('layouts.app')
@section('title', 'Hasil Tes Preferensi — Qammaris')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="bg-brand-cream pt-28 pb-10 md:pt-32 md:pb-16">
 <div class="container mx-auto max-w-6xl px-5">
  <p class="text-sm text-brand-black/60">Hasil tes · versi uji</p>
  <h1 class="mt-3 font-mayluxa text-4xl md:text-5xl">{{ $result['title'] }}</h1>
  <p class="mt-4 max-w-2xl text-brand-black/70">Pilihan dari katalog berdasarkan jawabanmu. Bukan kepastian selera atau janji ketahanan. Hasil bisa dibuka di browser ini sampai {{ $stored->expires_at->timezone('Asia/Makassar')->format('d M Y, H:i') }} WITA.</p>
  <div class="mt-5 flex flex-wrap gap-4 items-center">
    <a class="pref-link min-h-11 py-2" href="{{ route('quiz.index', ['edit' => $stored->id]) }}">Ubah jawaban</a>
    <form method="post" action="{{ route('quiz.v1.recalculate', $stored->id) }}" data-preference-recalculate>@csrf<button class="pref-link min-h-11 py-2" type="submit">Hitung ulang dengan katalog terbaru</button></form>
    <a class="pref-link min-h-11 py-2" href="{{ route('quiz.v1.show', [$stored->id, 'ready' => $readyOnly ? null : 1]) }}">{{ $readyOnly ? 'Tampilkan semua status' : 'Hanya yang Ready' }}</a>
  </div>
  @if($result['changed'])<p class="mt-5 border-l-2 border-brand-gold bg-white p-4">Data katalog berubah. Harga dan status di bawah sudah terbaru; alasan masih dari tes sebelumnya. Hitung ulang untuk memperbarui rekomendasi dan budget.</p>@endif
  @foreach($result['limitations'] as $limitation)<p class="mt-3 text-sm text-brand-black/70">{{ $limitation }}</p>@endforeach
  @if($result['empty'])
   <div class="my-8 border-t border-brand-black/20 pt-6"><h2 class="font-mayluxa text-3xl">Belum ada pilihan yang dapat ditampilkan</h2><p class="mt-3">Coba tampilkan semua status, ubah budget, atau sesuaikan aroma pilihanmu. Kami tidak menambahkan parfum tanpa kecocokan hanya untuk mengisi hasil.</p></div>
  @elseif(count($result['main']) < 3)
   <p class="mt-5 text-sm text-brand-black/70">Pilihan sesuai syaratmu terbatas. Berikut yang didukung data katalog.</p>
  @endif
  @foreach(['main' => 'Pilihan utama', 'alternative' => 'Alternatif di atas budget — maksimal 10% saat tes'] as $slot => $heading)
   @if($result[$slot])
   <section class="mt-9" aria-label="{{ $heading }}">
    <h2 class="font-mayluxa text-2xl">{{ $heading }}</h2>
    <div class="mt-5 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
     @foreach($result[$slot] as $row)
      <article class="bg-white border border-brand-black/10 p-5" data-result-product="{{ $row['product_id'] }}">
       <a href="{{ route('products.show', $row['product']->slug) }}"><img class="h-56 w-full object-contain" src="{{ $row['product']->primaryImage?->image_url ?? \App\Models\ProductImage::PLACEHOLDER_URL }}" alt="{{ $row['product']->name }}" loading="lazy"></a>
       <p class="mt-4 text-xs text-brand-black/60">{{ $row['product']->brand?->name }}</p>
       <h3 class="mt-1 font-mayluxa text-2xl"><a href="{{ route('products.show', $row['product']->slug) }}">{{ $row['product']->name }}</a></h3>
       <p class="mt-2">{{ $row['size_ml'] }} ml · {{ \App\Support\Rupiah::format($row['price']) }}</p>
       <p class="mt-2 text-sm font-semibold">{{ ['available' => 'Ready', 'sold_out' => 'Habis', 'unknown' => 'Tanyakan ketersediaan'][$row['availability']] ?? 'Tanyakan ketersediaan' }}</p>
       @if($row['sweetness_uncertain'] ?? false)<p class="mt-3 text-sm text-amber-900">Kemanisan belum diketahui. Coba tester untuk memastikan pilihan ini sesuai.</p>@endif
       @if($row['changed'])<p class="mt-3 text-sm text-amber-900">Data berubah sejak tes. Hitung ulang untuk memastikan pilihan ini masih sesuai.</p>@endif
       <ul class="mt-4 space-y-2 text-sm">@foreach($row['reasons'] as $reason)<li>{{ $reason['text'] }}</li>@endforeach</ul>
       <details class="mt-4 border-t border-brand-black/10 pt-3"><summary class="cursor-pointer min-h-11 text-sm">Kompromi dan batasan data</summary><ul class="space-y-2 text-sm text-brand-black/60">@foreach($row['limitations'] as $limitation)<li>{{ $limitation }}</li>@endforeach</ul></details>
       <a class="pref-link mt-3 inline-flex min-h-11 items-center" href="{{ route('products.show', $row['product']->slug) }}">Lihat parfum</a>
      </article>
     @endforeach
    </div>
   </section>
   @endif
  @endforeach
  <section class="mt-12 border-t border-brand-black/20 pt-7 max-w-3xl">
   <h2 class="font-mayluxa text-3xl">Rekomendasinya terasa sesuai?</h2>
   <p class="mt-2 text-sm text-brand-black/60">Nilai relevansi berdasarkan informasi di sini. Ini berbeda dari penilaian setelah mencium parfum.</p>
   <form data-preference-feedback action="{{ route('quiz.v1.feedback', $stored->id) }}" method="post" class="mt-5">
    @csrf
    <fieldset><legend class="font-semibold">Secara keseluruhan</legend><div class="mt-3 flex flex-wrap gap-3">
     @foreach(['suitable' => 'Sesuai', 'partly' => 'Sebagian sesuai', 'unsuitable' => 'Kurang sesuai'] as $value => $label)
      <label class="pref-option flex gap-3 items-center"><input type="radio" name="overall" value="{{ $value }}" @checked($feedback?->overall === $value)> {{ $label }}</label>
     @endforeach
    </div></fieldset>
    @foreach([...$result['main'], ...$result['alternative']] as $row)
     @php($saved = collect($feedback?->products ?? [])->firstWhere('product_id', $row['product_id']))
     <fieldset data-feedback-product="{{ $row['product_id'] }}" class="mt-6"><legend class="font-semibold">{{ $row['product']->name }} <span class="font-normal text-sm">(opsional)</span></legend>
      <label class="block mt-2 text-sm" for="rating-{{ $row['product_id'] }}">Ketertarikan</label>
      <select id="rating-{{ $row['product_id'] }}" data-rating class="mt-2 border border-brand-black/30 p-3 w-full"><option value="">Belum menilai</option>@foreach(['interesting' => 'Menarik', 'neutral' => 'Biasa saja', 'uninteresting' => 'Kurang menarik'] as $value => $label)<option value="{{ $value }}" @selected(($saved['rating'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
      <div class="mt-3 flex flex-wrap gap-4">@foreach(['aroma' => 'Karakter aroma', 'price' => 'Harga', 'availability' => 'Ketersediaan', 'unfamiliar' => 'Belum mengenal produknya'] as $value => $label)<label class="flex items-center gap-2 min-h-11 text-sm"><input type="checkbox" data-reason value="{{ $value }}" @checked(in_array($value, $saved['reasons'] ?? []))> {{ $label }}</label>@endforeach</div>
     </fieldset>
    @endforeach
    <button type="submit" class="pref-button mt-6">Simpan feedback</button>
    <p data-feedback-status role="status" class="mt-3 text-sm">{{ $feedback ? 'Feedback sebelumnya sudah tersimpan. Kamu boleh memperbaruinya.' : '' }}</p>
   </form>
  </section>
 </div>
</section>
@endsection
