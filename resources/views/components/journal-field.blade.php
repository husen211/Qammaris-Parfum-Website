@props(['name', 'label', 'value' => null, 'type' => 'text', 'rows' => 3])
<label for="{{ $name }}">{{ $label }}</label>
@if($type === 'textarea')
<textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="{{ $name }}-error" @endif {{ $attributes }}>{{ old($name, $value) }}</textarea>
@else
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'file') value="{{ old($name, $value) }}" @endif aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="{{ $name }}-error" @endif {{ $attributes }}>
@endif
@error($name)<p id="{{ $name }}-error" class="journal-field-error">{{ $message }}</p>@enderror
