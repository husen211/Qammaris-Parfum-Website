<?php

namespace App\Http\Controllers;

use App\Http\Requests\FragranceQuizRequest;
use App\Services\FragranceQuizService;
use Illuminate\Http\Request;

class FragranceQuizController extends Controller
{
    public function index(Request $request, FragranceQuizService $service)
    {
        $request->session()->forget(['quiz_answers', 'quiz_result_ready']);
        $questions = $service->questions();

        return view('quiz.index', [
            'questions' => $questions,
        ]);
    }

    public function store(FragranceQuizRequest $request)
    {
        $request->session()->put('quiz_answers', $request->validated());
        $request->session()->put('quiz_result_ready', true);

        return redirect()->route('quiz.result');
    }

    public function result(Request $request, FragranceQuizService $service)
    {
        $answers = $request->session()->get('quiz_answers', []);
        $ready = $request->session()->get('quiz_result_ready', false);

        if (! $ready || empty($answers)) {
            $request->session()->forget(['quiz_answers', 'quiz_result_ready']);

            return redirect()->route('quiz.index');
        }

        $result = $service->recommend($answers);
        $summary = $this->buildSummary($answers, $service->questions());

        $request->session()->forget(['quiz_answers', 'quiz_result_ready']);

        return view('quiz.result', [
            'result' => $result,
            'summary' => $summary,
        ]);
    }

    private function buildSummary(array $answers, array $questions): array
    {
        $summary = [];

        foreach ($questions as $key => $question) {
            $value = $answers[$key] ?? null;
            if (! $value) {
                continue;
            }

            $summary[] = [
                'label' => $question['label'],
                'value' => $question['options'][$value] ?? $value,
            ];
        }

        return $summary;
    }
}
