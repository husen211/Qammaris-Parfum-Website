<?php

namespace App\Http\Controllers;

use App\Http\Requests\FragrancePreferenceRequest;
use App\Models\FragranceQuizFeedback;
use App\Models\FragranceQuizResult;
use App\Models\Product;
use App\Services\FragranceQuizResults;
use App\Support\FragrancePreference\QuizQuestions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

final class FragrancePreferenceController extends Controller
{
    public function index(Request $request, FragranceQuizResults $results)
    {
        $answers = [];
        if ($request->filled('edit')) {
            $request->validate(['edit' => 'uuid']);
            $answers = $results->owned($request->query('edit'), $this->browser($request))->answers;
        }
        $favorites = Product::published()->with(['brand', 'activeOffer'])->orderBy('name')->limit(5000)->get()->map(fn ($product) => ['id' => $product->id, 'label' => $product->name.' — '.$product->brand?->name.' — '.$product->activeOffer?->volume.' ml'])->values();

        return view('quiz.v1.index', ['questions' => QuizQuestions::all(), 'answers' => $answers, 'favorites' => $favorites]);
    }

    public function store(FragrancePreferenceRequest $request, FragranceQuizResults $results)
    {
        try {
            $result = $results->create($request->answers(), $this->browser($request));
        } catch (\Throwable $error) {
            // No answers, cookie, SQL binding or request body in operator logs.
            Log::error('fragrance.quiz_create_failed', ['exception' => $error::class]);

            return response()->view('quiz.v1.error', ['retry' => route('quiz.index')], 503);
        }

        return redirect()->route('quiz.v1.show', $result->id, 303);
    }

    public function show(Request $request, string $id, FragranceQuizResults $results)
    {
        $this->enabled();
        $stored = $results->owned($id, $this->browser($request));
        $request->validate(['ready' => ['nullable', Rule::in(['1'])]]);

        return view('quiz.v1.result', ['stored' => $stored, 'result' => $results->display($stored, $request->query('ready') === '1'), 'readyOnly' => $request->query('ready') === '1', 'questions' => QuizQuestions::all(), 'feedback' => FragranceQuizFeedback::where('result_id', $id)->first()]);
    }

    public function recalculate(Request $request, string $id, FragranceQuizResults $results)
    {
        $this->enabled();
        $stored = $results->owned($id, $this->browser($request));
        try {
            $new = $results->create($stored->answers, $this->browser($request));
        } catch (\Throwable $error) {
            Log::error('fragrance.quiz_recalculate_failed', ['exception' => $error::class]);

            return response()->view('quiz.v1.error', ['retry' => route('quiz.v1.show', $id)], 503);
        }

        return redirect()->route('quiz.v1.show', $new->id, 303);
    }

    public function feedback(Request $request, string $id, FragranceQuizResults $results)
    {
        $this->enabled();
        $stored = $results->owned($id, $this->browser($request));
        $allowed = collect([...$stored->recommendations['main'], ...$stored->recommendations['alternative']])->pluck('product_id')->all();
        $data = $request->validate(['overall' => ['required', Rule::in(['suitable', 'partly', 'unsuitable'])], 'products' => ['present', 'array', 'list', 'max:4'], 'products.*' => ['array:product_id,rating,reasons'], 'products.*.product_id' => ['required', 'integer', 'distinct', Rule::in($allowed)], 'products.*.rating' => ['required', Rule::in(['interesting', 'neutral', 'uninteresting'])], 'products.*.reasons' => ['present', 'array', 'list', 'max:4'], 'products.*.reasons.*' => ['string', 'distinct', Rule::in(['aroma', 'price', 'availability', 'unfamiliar'])]]);
        abort_if(array_diff(array_keys($request->all()), ['_token', 'overall', 'products']), 422);
        $data['products'] = array_map(fn ($row) => [...$row, 'product_id' => (int) $row['product_id']], $data['products']);
        try {
            DB::transaction(function () use ($id, $data) {
                // Serialize feedback replays on the owning result, including the first insert.
                FragranceQuizResult::whereKey($id)->lockForUpdate()->firstOrFail();
                FragranceQuizFeedback::updateOrCreate(['result_id' => $id], $data);
            });
        } catch (\Throwable $error) {
            Log::error('fragrance.feedback_failed', ['exception' => $error::class]);

            return response()->json(['message' => 'Feedback belum tersimpan. Silakan coba lagi.'], 503);
        }

        return response()->json(['message' => 'Terima kasih, feedback tersimpan.']);
    }

    private function browser(Request $request): string
    {
        return $request->attributes->get('preference_browser_hash');
    }

    private function enabled(): void
    {
        abort_unless(config('fragrance_preference.enabled'), 404);
    }
}
