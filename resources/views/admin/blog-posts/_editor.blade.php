@push('styles') @vite('resources/js/blog-editor.js') @endpush
@php
    $editing = $blogPost !== null;
    $previewRoute = $editing ? route('admin.blog-posts.preview-existing', $blogPost) : route('admin.blog-posts.preview');
    $selectedTags = array_map('intval', old('tag_ids', $blogPost?->tags->pluck('id')->all() ?? []));
    $status = $editing && $blogPost->is_published && $blogPost->published_at ? ($blogPost->published_at->isFuture() ? 'Dijadwalkan' : 'Tayang') : 'Draft';
@endphp
<div class="journal-editor max-w-5xl">
    <div class="journal-heading"><div><p class="journal-eyebrow">QAMMARIS JOURNAL · {{ $status }}</p><h1>{{ $editing ? 'Edit artikel' : 'Artikel baru' }}</h1><p>Simpan draft kapan saja. Preview tidak menerbitkan atau menyimpan perubahan.</p></div><a href="{{ route('admin.blog-posts.index') }}">Kembali ke daftar</a></div>
    @if($errors->any())
        <div role="alert" class="journal-errors" tabindex="-1"><strong>Artikel belum tersimpan.</strong><ul>@foreach($errors->messages() as $field=>$messages)<li><a href="#{{ explode('.', $field)[0] }}">{{ $messages[0] }}</a></li>@endforeach</ul>
            @if($errors->has('revision') && $editing)<a href="{{ route('admin.blog-posts.edit', $blogPost) }}">Muat ulang data terbaru (isian di halaman ini akan diganti)</a>@endif
            <p>Pilih ulang file gambar bila ada.</p>
        </div>
    @endif
    @if($editing && $blogPost->is_published && !$blogPost->featured_image_alt)<p class="journal-notice">Artikel lama tetap tayang. Lengkapi teks alternatif gambar ketika meninjaunya.</p>@endif
    <form id="journal-form" method="POST" action="{{ $editing ? route('admin.blog-posts.update', $blogPost) : route('admin.blog-posts.store') }}" enctype="multipart/form-data">
        @csrf
        @if($editing) @method('PUT') <input id="revision" type="hidden" name="revision" value="{{ old('revision', $blogPost->revision) }}"> @endif
        <details open class="journal-section"><summary>Konten <span>Judul, ringkasan, dan isi artikel</span></summary><div class="journal-section-body">
            <x-journal-field name="title" label="Judul" :value="$blogPost?->title" maxlength="255"/>
            <x-journal-field name="subtitle" label="Pengantar artikel" type="textarea" :rows="2" :value="$blogPost?->subtitle" maxlength="2000"/>
            <x-journal-field name="excerpt" label="Ringkasan untuk kartu artikel" type="textarea" :value="$blogPost?->excerpt" maxlength="1000"/>
            <div class="journal-row"><div><label for="category">Kategori</label><select id="category" name="category" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" aria-describedby="category-error">@foreach($categories as $cat)<option value="{{ $cat->name }}" @selected(old('category', $blogPost?->category ?? 'Tips') === $cat->name)>{{ $cat->name }}{{ !$cat->is_active ? ' (nonaktif)' : '' }}</option>@endforeach</select><p id="category-error" class="journal-field-error">{{ $errors->first('category') }}</p></div><div><x-journal-field name="author" label="Penulis" :value="$blogPost?->author ?? 'Qammaris Editorial'" maxlength="100"/></div></div>
            <label id="body-label" for="content">Isi artikel</label>
            <div data-editor-controls hidden>
                <div class="journal-mode"><button type="button" data-mode="visual" aria-pressed="true">Visual</button><button type="button" data-mode="html" aria-pressed="false">HTML</button></div>
                <div data-editor-toolbar class="journal-toolbar" role="group" aria-label="Format artikel">
                    @foreach(['paragraph'=>'Paragraf', 'h2'=>'H2', 'h3'=>'H3', 'bold'=>'Tebal', 'italic'=>'Miring', 'bulletList'=>'Daftar', 'orderedList'=>'Nomor', 'blockquote'=>'Kutipan', 'horizontalRule'=>'Pemisah', 'insertTable'=>'Tabel', 'addRowAfter'=>'+ Baris', 'addColumnAfter'=>'+ Kolom', 'deleteTable'=>'Hapus tabel', 'undo'=>'Urungkan', 'redo'=>'Ulangi'] as $command=>$label)<button type="button" data-command="{{ $command }}">{{ $label }}</button>@endforeach
                </div>
                <div data-visual-editor></div>
                <div data-editor-inserts class="journal-inserts">
                    <details><summary>Tambahkan tautan</summary><label for="editor-link">URL tautan</label><input id="editor-link" type="url" placeholder="https://…"><button type="button" data-insert-link>Pasang tautan pada teks terpilih</button><button type="button" data-remove-link>Hapus tautan</button></details>
                    <details><summary>Tambahkan gambar dalam artikel</summary><label for="editor-image">URL gambar milik Anda</label><input id="editor-image" placeholder="/storage/blog/…"><label for="editor-image-alt">Teks alternatif gambar</label><input id="editor-image-alt" maxlength="255"><button type="button" data-insert-image>Tambahkan gambar</button></details>
                    @include('admin.blog-posts._components')
                </div>
            </div>
            <textarea id="content" name="content" rows="16" maxlength="500000" spellcheck="false" aria-invalid="{{ $errors->has('content') ? 'true' : 'false' }}" aria-describedby="editor-feedback content-error">{{ old('content', $blogPost?->content) }}</textarea>
            <p id="editor-feedback" role="status">Mode HTML tersedia jika editor visual tidak dapat dimuat. HTML dibersihkan saat preview dan penyimpanan.</p><p id="content-error" class="journal-field-error">{{ $errors->first('content') }}</p>
        </div></details>
        <details class="journal-section" @if($errors->has('featured_image') || $errors->has('featured_image_alt')) open @endif><summary>Media <span>Gambar utama dan teks alternatif</span></summary><div class="journal-section-body">
            @if($editing && $blogPost->featured_image)<img class="journal-current-image" src="{{ $blogPost->featured_image_url }}" alt="{{ $blogPost->featured_image_alt ?: $blogPost->title }}">@endif
            <x-journal-field name="featured_image" label="Gambar utama" type="file" accept="image/jpeg,image/png,image/webp"/>
            <p>JPEG, PNG, atau WebP · maksimal 5 MB. Gambar sebelumnya tetap disimpan.</p>
            <x-journal-field name="featured_image_alt" label="Teks alternatif gambar utama" :value="$blogPost?->featured_image_alt" maxlength="255" placeholder="Jelaskan isi gambar secara singkat"/>
            @if($editing)
                <p>Simpan perubahan artikel terlebih dahulu, lalu buka pengelola media. Kembali ke editor akan memuat revision terbaru.</p><a href="{{ route('admin.blog-media.index', $blogPost) }}">Upload media dan tinjau crop →</a>
                @if($mediaChoices->isNotEmpty())<label for="featured_media_id">Pilih gambar utama dari media artikel</label><select id="featured_media_id" name="featured_media_id"><option value="">Pertahankan gambar saat ini</option>@foreach($mediaChoices as $media)<option value="{{ $media->id }}" @selected((int)old('featured_media_id', $blogPost->featured_media_id) === $media->id)>{{ $media->alt ?: 'Media '.$media->id }}</option>@endforeach</select><p>Upload file utama baru didahulukan bila keduanya dipilih.</p>@endif
            @else<p>Simpan draft untuk mengunggah media tambahan, galeri, metadata hak pakai dan crop.</p>@endif
        </div></details>
        <details class="journal-section" @if(collect(['seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'og_image_url', 'seo_indexable', 'seo_followable'])->contains(fn ($field) => $errors->has($field))) open @endif><summary>SEO <span>Pencarian dan berbagi</span></summary><div class="journal-section-body">
            <x-journal-field name="seo_title" label="Judul SEO (opsional)" :value="$blogPost?->seo_title" maxlength="255"/><p>Kosongkan untuk memakai judul artikel.</p>
            <x-journal-field name="meta_description" label="Deskripsi pencarian (opsional)" type="textarea" :rows="2" :value="$blogPost?->meta_description" maxlength="160"/><p>Kosongkan untuk memakai ringkasan.</p>
            <x-journal-field name="canonical_url" label="Canonical pengganti (opsional)" type="url" :value="$blogPost?->canonical_url" maxlength="2048"/>
            <p>Kosongkan untuk URL artikel ini. Ganti hanya bila artikel utama ada di URL HTTPS lain.</p>
            @foreach(['seo_indexable'=>'Izinkan artikel diindeks mesin pencari', 'seo_followable'=>'Izinkan mesin pencari mengikuti tautan'] as $field=>$label)
                <input type="hidden" name="{{ $field }}" value="0"><label class="journal-check"><input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked(old($field, $blogPost?->{$field} ?? true))>{{ $label }}</label>
            @endforeach
            <x-journal-field name="og_title" label="Judul saat dibagikan (opsional)" :value="$blogPost?->og_title" maxlength="255"/>
            <x-journal-field name="og_description" label="Ringkasan saat dibagikan (opsional)" type="textarea" :rows="2" :value="$blogPost?->og_description" maxlength="1000"/>
            <x-journal-field name="og_image_url" label="URL HTTPS gambar saat dibagikan (opsional)" type="url" :value="$blogPost?->og_image_url" maxlength="2048"/>
            <p>Kosongkan untuk gambar utama. Jika diganti, gunakan URL file gambar milik Anda yang sudah tersedia; URL ini hanya metadata dan tidak diunduh.</p>
        </div></details>
        <details class="journal-section" @if($errors->has('tag_ids') || $errors->has('tag_ids.*')) open @endif><summary>Relasi <span>Tag dan tautan produk</span></summary><div class="journal-section-body">
            <input type="hidden" name="tags_present" value="1"><fieldset id="tag_ids"><legend>Tag artikel</legend><div class="journal-tags">@forelse($tags as $tag)<label><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true))>{{ $tag->name }}{{ !$tag->is_active ? ' (nonaktif)' : '' }}</label>@empty<p>Belum ada tag.</p>@endforelse</div></fieldset>
            <a href="{{ route('admin.blog-taxonomy.index') }}" target="_blank" rel="noopener">Kelola kategori dan tag ↗</a>
            <div data-product-insert hidden><label for="editor-product">Produk katalog</label><select id="editor-product"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select><button type="button" data-insert-product>Tambahkan tautan produk ke artikel</button></div>
            @include('admin.blog-posts._relations')
        </div></details>
        <details open class="journal-section"><summary>Publikasi <span>Draft, jadwal, dan artikel unggulan</span></summary><div class="journal-section-body">
            <input type="hidden" name="is_featured" value="0"><label class="journal-check"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $blogPost?->is_featured))>Tandai sebagai artikel unggulan</label>
            <label for="is_published">Saat disimpan</label><select id="is_published" name="is_published"><option value="0" @selected(!old('is_published', $blogPost?->is_published))>Simpan sebagai draft</option><option value="1" @selected(old('is_published', $blogPost?->is_published))>Tayangkan / jadwalkan</option></select>
            <x-journal-field name="published_at" label="Waktu tayang ({{ config('app.timezone') }})" type="datetime-local" :value="$blogPost?->published_at?->format('Y-m-d\TH:i')"/>
            <p>Artikel baru: kosongkan agar tayang sekarang. Saat mengedit, waktu lama dipertahankan bila dikosongkan. Draft tidak tampil di publik.</p>
        </div></details>
        <div class="journal-actions"><button type="submit" formaction="{{ $previewRoute }}" formtarget="_blank" data-preview>Preview ↗</button><button type="submit" class="journal-primary">{{ $editing ? 'Simpan perubahan' : 'Simpan artikel' }}</button><p data-save-feedback role="status"></p></div>
    </form>
</div>
