@foreach ($catalogState->query(includePage: false) as $key => $value)
    @continue(in_array($key, $exclude ?? [], true))

    @if (is_array($value))
        @foreach ($value as $item)
            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
@endforeach
