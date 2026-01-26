<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FragranceQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $questions = config('fragrance_quiz.questions', []);
        $rules = [];

        foreach ($questions as $key => $question) {
            $options = array_keys($question['options'] ?? []);
            $rules[$key] = ['required', Rule::in($options)];
        }

        return $rules;
    }
}
