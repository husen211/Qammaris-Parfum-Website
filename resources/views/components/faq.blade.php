@props(['items', 'name' => 'faq-'.\Illuminate\Support\Str::uuid()])
{{-- Blade adaptation of anshuman008's FAQ Chat Accordion (21st demo 1517). --}}
<div {{ $attributes->class(['q-faq']) }}>
    @foreach ($items as $item)
        <details class="q-faq-item" name="{{ $name }}">
            <summary class="q-faq-question">
                <span class="q-faq-question-bubble">{{ $item['question'] }}</span>
                <span class="q-faq-toggle"><x-icon name="plus" class="q-faq-plus" /><x-icon name="minus" class="q-faq-minus" /></span>
            </summary>
            <div class="q-faq-answer"><p>{{ $item['answer'] }}</p></div>
        </details>
    @endforeach
</div>
