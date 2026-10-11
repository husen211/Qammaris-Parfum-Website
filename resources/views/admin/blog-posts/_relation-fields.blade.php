@if(in_array($field, ['related_product_ids', 'related_article_ids']))
    <label>Pilih {{ $field === 'related_product_ids' ? 'produk' : 'artikel' }}<select name="{{ $field }}[{{ $index }}]" data-field="" required><option value="">Pilih</option>
        @foreach($field === 'related_product_ids' ? $products : $articleChoices as $choice)<option value="{{ $choice->id }}" @selected((int)$row === $choice->id)>{{ $choice->name ?? $choice->title }}</option>@endforeach
    </select></label>
@else
    @foreach($field === 'faqs' ? ['question'=>'Pertanyaan', 'answer'=>'Jawaban'] : ['title'=>'Judul referensi', 'url'=>'URL HTTPS referensi'] as $key=>$label)
        <label>{{ $label }}@if($key === 'answer')<textarea name="{{ $field }}[{{ $index }}][{{ $key }}]" data-field="{{ $key }}" required maxlength="2000" rows="3">{{ $row[$key] ?? '' }}</textarea>@else<input name="{{ $field }}[{{ $index }}][{{ $key }}]" data-field="{{ $key }}" value="{{ $row[$key] ?? '' }}" type="{{ $key === 'url' ? 'url' : 'text' }}" maxlength="{{ $key === 'url' ? 2048 : 255 }}" required>@endif</label>
    @endforeach
@endif
