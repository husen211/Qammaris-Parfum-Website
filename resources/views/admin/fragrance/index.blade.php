@extends('layouts.admin')
@section('content')
<div class="max-w-5xl space-y-6">
 <h1 class="text-2xl font-semibold">Tes preferensi — evaluasi versi uji</h1>
 <p>Feedback ini menilai relevansi informasi, bukan pengalaman setelah mencium parfum. Tidak ada perubahan bobot otomatis.</p>
 @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 <form class="flex flex-wrap gap-3" method="get"><label>Versi mesin<select name="version" class="border p-3"><option value="">Semua</option>@foreach($versions as $version)<option @selected(request('version') === $version)>{{ $version }}</option>@endforeach</select></label><label>ID produk<input type="number" min="1" name="product" value="{{ request('product') }}" class="border p-3"></label><button class="pref-button">Filter</button></form>
 <p>{{ $total }} hasil tersimpan · {{ $empty }} hasil kosong pada saat tes. Feedback sesuai: {{ $counts['suitable'] ?? 0 }} · sebagian: {{ $counts['partly'] ?? 0 }} · kurang: {{ $counts['unsuitable'] ?? 0 }}.</p>
 <form method="post" action="{{ route('admin.fragrance.preview') }}">@csrf<button class="pref-button">Preview pembentukan profil terkini</button></form>
 @if($preview)<div class="bg-white border p-4"><p>{{ $preview['count'] }} produk dipreview; {{ $preview['changes'] }} profil perlu dibentuk. Hanya tabel rekomendasi yang ditulis.</p><form method="post" action="{{ route('admin.fragrance.apply') }}">@csrf<input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}"><button class="pref-button mt-3">Terapkan preview ini</button></form></div>@endif
 <h2 class="text-xl">Feedback terbaru</h2>
 @forelse($feedback as $entry)<article class="border-b py-4"><p>{{ $entry->created_at->format('d M Y H:i') }} · {{ ['suitable'=>'Sesuai','partly'=>'Sebagian sesuai','unsuitable'=>'Kurang sesuai'][$entry->overall] }}</p>@foreach($entry->products as $rating)<p class="mt-2">Produk <a class="underline" href="{{ route('admin.fragrance.profile', $rating['product_id']) }}">#{{ $rating['product_id'] }}</a> · {{ ['interesting'=>'Menarik','neutral'=>'Biasa saja','uninteresting'=>'Kurang menarik'][$rating['rating']] }} · {{ implode(', ', $rating['reasons']) }}</p>@endforeach</article>@empty<p>Belum ada feedback untuk filter ini.</p>@endforelse
 {{ $feedback->links() }}
 <p class="text-sm">Evaluasi 30 skenario tetap dijalankan melalui evaluator offline; daftar hasil bukan klaim akurasi. Jawaban dan feedback anonim dipertahankan tanpa penghapusan otomatis.</p>
</div>
@endsection
