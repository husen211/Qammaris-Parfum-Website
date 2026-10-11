<input type="hidden" name="components_present" value="1">
@foreach(['related_product_ids'=>'Produk pilihan (maks. 12)', 'related_article_ids'=>'Artikel terkait (maks. 3)', 'faqs'=>'Pertanyaan umum (maks. 12)', 'references'=>'Referensi (maks. 20)'] as $field=>$label)
    @php $rows = old($field, $blogPost?->{$field} ?? []); $limit = ['related_product_ids'=>12, 'related_article_ids'=>3, 'faqs'=>12, 'references'=>20][$field]; @endphp
    <fieldset id="{{ $field }}" data-repeat="{{ $field }}" data-limit="{{ $limit }}"><legend>{{ $label }}</legend><p>Urutan di bawah menjadi urutan tampil. Pilihan yang belum tayang tidak muncul di publik.</p>
        <div data-repeat-rows>@foreach($rows as $index=>$row)<div data-repeat-row>@include('admin.blog-posts._relation-fields') @include('admin.blog-posts._row-actions')</div>@endforeach</div>
        <template><div data-repeat-row>@include('admin.blog-posts._relation-fields', ['index'=>0, 'row'=>null]) @include('admin.blog-posts._row-actions')</div></template>
        <button type="button" data-repeat-add>Tambah {{ $field === 'faqs' ? 'pertanyaan' : ($field === 'references' ? 'referensi' : 'pilihan') }}</button><p class="journal-field-error">{{ $errors->first($field) }}</p>
    </fieldset>
@endforeach
<p data-repeat-status role="status"></p>
