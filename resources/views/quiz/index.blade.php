@extends('layouts.app')

@section('title', 'Tes Preferensi Parfum')

@section('content')
    <section class="py-16 md:py-24 bg-brand-cream border-b border-gray-200">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center" data-reveal>
                <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Fragrance Finder</p>
                <h1 class="font-mayluxa text-4xl md:text-5xl text-brand-black mt-3">
                    Tes Preferensi Parfum
                </h1>
                <p class="mt-4 text-sm text-brand-black/60">
                    Jawab pertanyaan satu per satu, hasilnya langsung kita sesuaikan dengan katalog.
                </p>
            </div>

            @php
                $totalSteps = count($questions);
                $initialStep = 0;
            @endphp

            <div class="mt-12 grid gap-10 lg:grid-cols-[2fr,1fr]">
                <form id="quiz-form" action="{{ route('quiz.store') }}" method="POST"
                    class="border border-brand-black/10 bg-white/90 p-8 md:p-10 shadow-[0_24px_48px_rgba(0,0,0,0.08)]"
                    data-quiz data-initial-step="{{ $initialStep }}">
                    @csrf
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Step</p>
                            <h2 class="font-mayluxa text-3xl text-brand-black mt-2" id="quiz-step-label">1 dari {{ $totalSteps }}</h2>
                        </div>
                    </div>

                    <div class="mt-6 h-1 bg-brand-black/10">
                        <div id="quiz-progress" class="h-full bg-brand-gold transition-all duration-300" style="width: 0%;"></div>
                    </div>

                    <div id="quiz-steps" class="relative mt-10 min-h-[340px]">
                        @foreach ($questions as $key => $question)
                            @php
                                $selected = old($key, $answers[$key] ?? '');
                                $stepIndex = $loop->index;
                            @endphp
                            <div class="quiz-step absolute inset-0 opacity-0 translate-x-6 pointer-events-none transition-all duration-300"
                                data-quiz-step="{{ $stepIndex }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.3em] text-brand-black/40">Pertanyaan {{ $loop->iteration }}</p>
                                        <h3 class="font-mayluxa text-2xl text-brand-black mt-2">{{ $question['label'] }}</h3>
                                        <p class="mt-2 text-sm text-brand-black/60">{{ $question['helper'] }}</p>
                                    </div>
                                    <span class="text-xs uppercase tracking-[0.2em] text-brand-gold">Q{{ $loop->iteration }}</span>
                                </div>

                                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                    @foreach ($question['options'] as $value => $label)
                                        @php
                                            $inputId = $key . '-' . $value;
                                        @endphp
                                    <label for="{{ $inputId }}" class="group cursor-pointer">
                                        <input id="{{ $inputId }}" type="radio" name="{{ $key }}" value="{{ $value }}"
                                            class="peer sr-only" @checked($selected === $value) />
                                        <div class="relative flex items-center justify-between border border-brand-black/10 bg-white px-4 py-4 pr-12 text-sm text-brand-black/70 transition-all duration-300 peer-checked:border-brand-gold peer-checked:bg-brand-gold/10 peer-checked:text-brand-black group-hover:border-brand-gold/60 after:absolute after:right-4 after:top-1/2 after:h-4 after:w-4 after:-translate-y-1/2 after:border after:border-brand-black/30 after:bg-white after:transition-all after:content-[''] peer-checked:after:border-brand-gold peer-checked:after:bg-brand-gold">
                                            <span>{{ $label }}</span>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>

                                <p class="quiz-error mt-4 text-xs text-red-600 hidden">Pilih salah satu jawaban dulu.</p>

                                @error($key)
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
                        <button id="quiz-next" type="button" data-next-label="Next" data-submit-label="Lihat Rekomendasi"
                            class="inline-flex items-center justify-center bg-brand-black px-8 py-3 text-[11px] font-semibold uppercase tracking-widest text-white hover:bg-brand-gold hover:text-brand-black transition-all duration-300 active:scale-95">
                            Next
                        </button>
                    </div>
                </form>

                <aside class="border border-brand-black/10 bg-white/80 p-8 shadow-[0_24px_48px_rgba(0,0,0,0.08)]" data-reveal>
                    <p class="text-xs uppercase tracking-[0.3em] text-brand-black/50">Quiz Steps</p>
                    <h3 class="font-mayluxa text-2xl text-brand-black mt-3">Daftar Pertanyaan</h3>
                    <div class="mt-6 space-y-4">
                        @foreach ($questions as $key => $question)
                            <div class="flex items-start gap-4 text-sm text-brand-black/60" data-step-item="{{ $loop->index }}">
                                <span class="mt-1 inline-flex h-6 w-6 items-center justify-center border border-brand-black/20 text-[11px] font-semibold">
                                    {{ $loop->iteration }}
                                </span>
                                <div>
                                    <p class="font-semibold text-brand-black/70">{{ $question['label'] }}</p>
                                    <p class="text-xs text-brand-black/50">{{ $question['helper'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </aside>
            </div>

        </div>
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        const quizForm = document.querySelector('[data-quiz]');
        if (!quizForm) return;

        const steps = Array.from(document.querySelectorAll('[data-quiz-step]'));
        const stepItems = Array.from(document.querySelectorAll('[data-step-item]'));
        const total = steps.length;
        const progress = document.getElementById('quiz-progress');
        const stepLabel = document.getElementById('quiz-step-label');
        const nextButton = document.getElementById('quiz-next');
        const initialStep = Number.parseInt(quizForm.dataset.initialStep || '0', 10) || 0;
        let currentStep = Math.min(Math.max(initialStep, 0), total - 1);

        const updateStep = () => {
            steps.forEach((step, index) => {
                const isActive = index === currentStep;
                step.classList.toggle('opacity-0', !isActive);
                step.classList.toggle('translate-x-6', !isActive);
                step.classList.toggle('pointer-events-none', !isActive);
                step.classList.toggle('relative', isActive);
                step.classList.toggle('absolute', !isActive);
            });

            stepItems.forEach((item, index) => {
                if (index === currentStep) {
                    item.classList.add('text-brand-black');
                    item.classList.remove('text-brand-black/60');
                } else {
                    item.classList.remove('text-brand-black');
                    item.classList.add('text-brand-black/60');
                }
            });

            if (stepLabel) {
                stepLabel.textContent = `${currentStep + 1} dari ${total}`;
            }
            if (progress) {
                const percent = ((currentStep + 1) / total) * 100;
                progress.style.width = `${percent}%`;
            }

            const isLastStep = currentStep === total - 1;
            const nextLabel = nextButton.dataset.nextLabel || 'Next';
            const submitLabel = nextButton.dataset.submitLabel || 'Lihat Rekomendasi';
            nextButton.textContent = isLastStep ? submitLabel : nextLabel;
        };

        const showError = (step) => {
            const error = step.querySelector('.quiz-error');
            if (!error) return;
            error.classList.remove('hidden');
        };

        const clearError = (step) => {
            const error = step.querySelector('.quiz-error');
            if (!error) return;
            error.classList.add('hidden');
        };

        const isStepAnswered = (step) => {
            const selected = step.querySelector('input[type="radio"]:checked');
            return Boolean(selected);
        };

        nextButton.addEventListener('click', () => {
            const activeStep = steps[currentStep];
            if (!isStepAnswered(activeStep)) {
                showError(activeStep);
                return;
            }
            clearError(activeStep);
            if (currentStep === total - 1) {
                quizForm.submit();
                return;
            }
            currentStep = Math.min(currentStep + 1, total - 1);
            updateStep();
        });

        steps.forEach((step) => {
            step.addEventListener('change', () => clearError(step));
        });

        updateStep();
    })();
</script>
@endpush
