@php
    $failedForm = $errors->any() && (int)old('media_id') === ($item?->id ?? 0);
    $mediaValue = fn($field, $default = null) => $failedForm ? old($field, $item?->{$field} ?? $default) : ($item?->{$field} ?? $default);
@endphp
<label for="{{ $prefix }}-alt">Teks alternatif</label><input id="{{ $prefix }}-alt" name="alt" value="{{ $mediaValue('alt') }}" required maxlength="255">
<label for="{{ $prefix }}-caption">Caption (opsional)</label><textarea id="{{ $prefix }}-caption" name="caption" maxlength="2000" rows="2">{{ $mediaValue('caption') }}</textarea>
<label for="{{ $prefix }}-credit">Credit / pemilik foto</label><input id="{{ $prefix }}-credit" name="credit" value="{{ $mediaValue('credit') }}" maxlength="255">
<label for="{{ $prefix }}-source">Sumber HTTPS (jika foto eksternal)</label><input type="url" id="{{ $prefix }}-source" name="source_url" value="{{ $mediaValue('source_url') }}" maxlength="2048">
<label for="{{ $prefix }}-license">Hak pakai / izin</label><input id="{{ $prefix }}-license" name="license" value="{{ $mediaValue('license', 'Milik Qammaris') }}" required maxlength="255">
<div class="journal-row"><div><label for="{{ $prefix }}-crop">Rasio tampilan</label><select id="{{ $prefix }}-crop" name="crop" data-crop-select>@foreach(['original'=>'Gambar utuh', '16:9'=>'16:9', '4:3'=>'4:3', '1:1'=>'1:1'] as $value=>$label)<option value="{{ $value }}" @selected($mediaValue('crop', 'original') === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="{{ $prefix }}-x">Titik fokus horizontal (0–100%)</label><input id="{{ $prefix }}-x" type="number" name="focal_x" min="0" max="100" value="{{ $mediaValue('focal_x', 50) }}" required data-crop-x><label for="{{ $prefix }}-y">Titik fokus vertikal (0–100%)</label><input id="{{ $prefix }}-y" type="number" name="focal_y" min="0" max="100" value="{{ $mediaValue('focal_y', 50) }}" required data-crop-y></div></div>
<p>Preview di bawah menunjukkan area crop. Gambar asli tersedia lewat tautan. Periksa hasil tersimpan sebelum menerbitkan.</p>
<div class="journal-crop-preview" data-crop-preview @unless($item) hidden @endunless><img @if($item) src="{{ $item->url }}" @else hidden @endif alt="Preview area crop" data-crop-image></div>
