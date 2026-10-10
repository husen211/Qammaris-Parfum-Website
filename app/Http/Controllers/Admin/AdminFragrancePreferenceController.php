<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FragranceProfile;
use App\Models\FragranceQuizFeedback;
use App\Models\FragranceQuizResult;
use App\Models\Product;
use App\Services\FragranceProfileStore;
use Illuminate\Http\Request;

final class AdminFragrancePreferenceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['version' => ['nullable', 'string', 'max:60'], 'product' => ['nullable', 'integer', 'min:1']]);
        $results = FragranceQuizResult::query()->when($request->filled('version'), fn ($q) => $q->where('engine_version', $request->query('version')));
        $feedback = FragranceQuizFeedback::whereIn('result_id', (clone $results)->select('id'))->when($request->filled('product'), fn ($q) => $q->whereJsonContains('products', ['product_id' => (int) $request->query('product')]));

        return view('admin.fragrance.index', ['versions' => FragranceQuizResult::distinct()->pluck('engine_version'), 'total' => (clone $results)->count(), 'empty' => (clone $results)->where('recommendations->empty', true)->count(), 'counts' => (clone $feedback)->selectRaw('overall, COUNT(*) AS total')->groupBy('overall')->pluck('total', 'overall'), 'feedback' => $feedback->latest()->paginate(30)->withQueryString(), 'preview' => $request->session()->get('fragrance_profile_preview')]);
    }

    public function preview(Request $request, FragranceProfileStore $store)
    {
        $preview = $store->preview();
        $request->session()->put('fragrance_profile_preview', ['fingerprint' => $preview['fingerprint'], 'count' => count($preview['rows']), 'changes' => count(array_filter($preview['rows'], fn ($row) => $row['action'] === 'build'))]);

        return redirect()->route('admin.fragrance.index');
    }

    public function apply(Request $request, FragranceProfileStore $store)
    {
        $request->validate(['fingerprint' => ['required', 'regex:/\A[a-f0-9]{64}\z/']]);
        abort_unless(hash_equals((string) $request->session()->get('fragrance_profile_preview.fingerprint', ''), $request->input('fingerprint')), 409);
        try {
            $outcome = $store->apply($request->input('fingerprint'), $request->user());
        } catch (\DomainException) {
            return back()->withErrors(['profiles' => 'Sumber berubah. Buat preview baru sebelum menerapkan.']);
        }
        $request->session()->forget('fragrance_profile_preview');

        return redirect()->route('admin.fragrance.index')->with('status', $outcome['changed'].' profil rekomendasi diperbarui. Katalog tidak berubah.');
    }

    public function profile(Product $product, FragranceProfileStore $store)
    {
        $profile = FragranceProfile::where('product_id', $product->id)->firstOrFail();

        return view('admin.fragrance.profile', ['product' => $product, 'profile' => $profile, 'effective' => $store->effective($profile, $product)]);
    }

    public function review(Request $request, Product $product, FragranceProfileStore $store)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'source' => ['required', 'regex:/\A[a-f0-9]{64}\z/'], 'changes' => ['required', 'json', 'max:4000'], 'evidence' => ['required', 'string', 'min:8', 'max:2000']]);
        try {
            $store->review($product->id, (int) $data['revision'], $data['source'], json_decode($data['changes'], true, 8, JSON_THROW_ON_ERROR), $data['evidence'], $request->user());
        } catch (\InvalidArgumentException|\JsonException|\TypeError) {
            return back()->withErrors(['changes' => 'Koreksi tidak valid. Gunakan atribut dan nilai yang didukung.']);
        } catch (\DomainException) {
            return back()->withErrors(['changes' => 'Profil atau sumber berubah. Bangun ulang profil lalu review kembali.']);
        }

        return redirect()->route('admin.fragrance.profile', $product->id)->with('status', 'Koreksi profil tersimpan dengan bukti dan revision.');
    }
}
