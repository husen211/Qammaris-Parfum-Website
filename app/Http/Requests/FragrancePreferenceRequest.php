<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\FragrancePreference\PreferenceAnswers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FragrancePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('fragrance_preference.enabled');
    }

    public function rules(): array
    {
        return [
            'budget_max' => ['present', 'nullable', 'integer', 'min:1', 'max:99999999'],
            'use' => ['required', Rule::in(['daily', 'office', 'casual', 'event', 'any'])],
            'environment' => ['required', Rule::in(['ac', 'outdoor', 'mixed', 'any'])],
            'likes' => ['present', 'array', 'list', 'max:3'], 'likes.*' => ['string', 'distinct', Rule::in(PreferenceAnswers::FAMILIES)],
            'avoid' => ['present', 'array', 'list', 'max:12'], 'avoid.*' => ['string', 'distinct', Rule::in(PreferenceAnswers::FAMILIES)],
            'sweetness' => ['required', Rule::in(['light', 'medium', 'sweet', 'any'])],
            'projection' => ['required', Rule::in(['close', 'medium', 'strong', 'unknown'])],
            'longevity' => ['required', Rule::in(['not_priority', 'few_hours', 'all_day', 'unknown'])],
            'gender' => ['nullable', Rule::in(['pria', 'wanita', 'all'])],
            'favorite_product_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['*.required' => 'Lengkapi pilihan ini terlebih dahulu.', '*.present' => 'Lengkapi pilihan ini atau pilih belum tahu.', '*.in' => 'Pilih jawaban yang tersedia.', '*.integer' => 'Gunakan angka rupiah bulat.', '*.max' => 'Pilihan atau angkanya melebihi batas.', '*.list' => 'Format pilihan tidak valid.', '*.distinct' => 'Pilihan tidak boleh berulang.'];
    }

    public function after(): array
    {
        return [function ($validator) {
            $allowed = [...array_keys($this->rules()), '_token'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add('answers', 'Ada isian yang tidak dikenali.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (array_intersect($this->input('likes', []), $this->input('avoid', []))) {
                $validator->errors()->add('avoid', 'Aroma yang disukai tidak boleh sekaligus dihindari.');
            }
            $favorite = $this->input('favorite_product_id');
            if ($favorite !== null && ! Product::published()->whereKey($favorite)->exists()) {
                $validator->errors()->add('favorite_product_id', 'Pilih parfum dari katalog yang tersedia atau lewati.');
            }
        }];
    }

    public function answers(): array
    {
        $answers = $this->validated();
        $answers['budget_max'] = isset($answers['budget_max']) ? (int) $answers['budget_max'] : null;
        $answers['favorite_product_id'] = isset($answers['favorite_product_id']) ? (int) $answers['favorite_product_id'] : null;
        $answers['gender'] = $answers['gender'] ?? 'all';

        return PreferenceAnswers::validate($answers);
    }
}
