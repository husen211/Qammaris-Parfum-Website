@extends('layouts.admin')
@section('content')
<div class="max-w-2xl space-y-6">
    <a class="inline-flex min-h-11 items-center text-sm underline underline-offset-4 focus-visible:outline focus-visible:outline-2" href="{{ route('admin.blog-posts.edit', $blogPost) }}">← Kembali ke editor</a>
    <div><h1 class="text-2xl font-semibold">Akses agent untuk draft</h1><p class="mt-2 text-gray-600 break-words">{{ $blogPost->title }}</p></div>
    <p class="text-sm leading-relaxed text-gray-600">Agent yang dipilih dapat membaca, mengedit, dan menambahkan foto pada draft ini. Agent tidak dapat menerbitkan artikel. Pilih “Tanpa agent” untuk mencabut aksesnya. Token dikelola terpisah dan tidak ditampilkan di sini.</p>
    @if(!config('blog_automation.enabled'))<p class="border-l-2 border-amber-600 pl-4 text-sm leading-relaxed">API agent belum diaktifkan. Pilihan ini hanya mencatat siapa yang boleh mengerjakan draft setelah aktivasi.</p>@endif
    @if($errors->any())<div role="alert" class="border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Akses belum tersimpan.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@if($errors->has('revision'))<a class="inline-flex min-h-11 items-center underline" href="{{ route('admin.blog-automation.edit', $blogPost) }}">Muat ulang data terbaru</a>@endif</div>@endif
    @if($blogPost->is_published || $blogPost->archived_at)
        <p class="text-sm text-gray-600">Artikel ini bukan draft. Simpan sebagai draft di editor sebelum memberikan akses agent.</p>
    @else
        <form method="POST" action="{{ route('admin.blog-automation.update', $blogPost) }}" class="space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="revision" value="{{ old('revision', $blogPost->revision) }}">
            <div><label for="automation_actor_id" class="block text-sm font-medium">Agent yang boleh mengedit</label>
                <select id="automation_actor_id" name="automation_actor_id" class="mt-2 block min-h-11 w-full border border-gray-300 bg-white px-3 py-2 text-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900" @if($errors->has('automation_actor_id')) aria-invalid="true" @endif aria-describedby="assignment-help">
                    <option value="">Tanpa agent</option>
                    @foreach($actors as $actor)<option value="{{ $actor->id }}" @selected((string) old('automation_actor_id', $blogPost->automation_actor_id) === (string) $actor->id) @disabled(!$actor->is_active)>{{ $actor->name }}{{ !$actor->is_active ? ' (nonaktif)' : '' }}</option>@endforeach
                </select>
                <p id="assignment-help" class="mt-2 text-sm text-gray-500">Hanya satu agent dapat mengedit draft ini. Mengganti pilihan mencabut akses agent sebelumnya.</p>
                @if($actors->where('is_active', true)->isEmpty())<p class="mt-2 text-sm text-gray-500">Belum ada agent aktif. Akun agent dibuat saat aktivasi API, bukan melalui akun admin website.</p>@endif
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-gray-900 px-5 py-3 text-sm font-medium text-white active:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900">Simpan akses draft</button>
        </form>
    @endif
</div>
@endsection
