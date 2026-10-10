<?php

namespace Tests\Feature;

use App\Http\Middleware\FragranceBrowser;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FragranceProfile;
use App\Models\FragranceQuizFeedback;
use App\Models\FragranceQuizResult;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FragranceProfileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FragrancePreferenceHttpTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['fragrance_preference.enabled' => true, 'app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $brand = Brand::create(['name' => 'Synthetic quiz brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $this->product = Product::create(['brand_id' => $brand->id, 'category_id' => $category->id, 'name' => 'Synthetic citrus', 'description' => 'Kekuatan Aroma: Kuat', 'fragrance_notes' => ['top' => ['Bergamot'], 'middle' => ['Jasmine'], 'base' => ['Musk']], 'gender' => 'Unisex', 'publication_status' => 'published', 'is_active' => true, 'availability_status' => 'available', 'availability_source' => 'qammaris_app']);
        ProductVariant::create(['product_id' => $this->product->id, 'volume' => 50, 'price' => 200000, 'is_active' => true]);
        $store = app(FragranceProfileStore::class);
        $store->apply($store->preview()['fingerprint'], $this->admin);
        $this->withCredentials()->withCookie(FragranceBrowser::COOKIE, str_repeat('a', 64));
    }

    private function answers(): array
    {
        return ['budget_max' => 300000, 'likes' => ['citrus'], 'avoid' => [], 'use' => 'any', 'environment' => 'any', 'sweetness' => 'any', 'projection' => 'strong', 'longevity' => 'not_priority', 'gender' => 'all', 'favorite_product_id' => null];
    }

    private function submit(): FragranceQuizResult
    {
        $this->postJson('/fragrance-quiz', $this->answers())->assertStatus(303);

        return FragranceQuizResult::latest()->firstOrFail();
    }

    public function test_wizard_and_result_keep_anonymous_ownership_private_cache_and_answers(): void
    {
        $this->get('/fragrance-quiz')->assertOk()->assertSee('Pertanyaan 1 dari 10')->assertSee('versi uji');
        $stored = $this->submit();
        $this->assertEquals($this->answers(), array_intersect_key($stored->answers, $this->answers()));
        $this->assertNotSame(str_repeat('a', 64), $stored->browser_hash);
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertOk()->assertSee('Synthetic citrus')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertOk();
        $this->withCookie(FragranceBrowser::COOKIE, str_repeat('b', 64))->get('/fragrance-quiz/results/'.$stored->id)->assertNotFound();
        $this->withCredentials()->withCookie(FragranceBrowser::COOKIE, str_repeat('a', 64));
        $this->travel(7)->days();
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertStatus(410);
        $this->assertDatabaseCount('fragrance_quiz_results', 1);
    }

    public function test_validation_rejects_conflicting_aromas_unknown_fields_and_private_favorite(): void
    {
        $this->postJson('/fragrance-quiz', [...$this->answers(), 'avoid' => ['citrus']])->assertUnprocessable()->assertJsonValidationErrors('avoid');
        $this->postJson('/fragrance-quiz', [...$this->answers(), 'phone' => 'synthetic'])->assertUnprocessable();
        $this->postJson('/fragrance-quiz', [...$this->answers(), 'budget_max' => 300000.5])->assertUnprocessable();
        $this->product->update(['publication_status' => 'draft']);
        $this->postJson('/fragrance-quiz', [...$this->answers(), 'favorite_product_id' => $this->product->id])->assertUnprocessable();
        $this->assertDatabaseCount('fragrance_quiz_results', 0);
    }

    public function test_current_catalog_hides_drafts_and_marks_changed_price_without_rewriting_snapshot(): void
    {
        $stored = $this->submit();
        $this->product->activeOffer->update(['price' => 400000]);
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertOk()->assertSee('Rp 400.000')->assertSee('Data katalog berubah');
        $this->assertSame('200000.00', $stored->fresh()->recommendations['main'][0]['price']);
        $this->product->update(['publication_status' => 'draft']);
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertOk()->assertDontSee('Synthetic citrus')->assertSee('Belum ada pilihan');
    }

    public function test_ready_filter_edit_recalculation_and_feedback_versions_are_separate(): void
    {
        $stored = $this->submit();
        $this->product->update(['availability_status' => 'sold_out']);
        $this->get('/fragrance-quiz/results/'.$stored->id.'?ready=1')->assertOk()->assertDontSee('Synthetic citrus');
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertOk()->assertSee('Habis');
        $this->get('/fragrance-quiz?edit='.$stored->id)->assertOk();
        $this->post('/fragrance-quiz/results/'.$stored->id.'/recalculate')->assertStatus(303);
        $this->assertDatabaseCount('fragrance_quiz_results', 2);
    }

    public function test_feedback_is_scoped_replay_safe_and_rejects_unrelated_products_and_free_text(): void
    {
        $stored = $this->submit();
        $url = '/fragrance-quiz/results/'.$stored->id.'/feedback';
        $data = ['overall' => 'partly', 'products' => [['product_id' => $this->product->id, 'rating' => 'interesting', 'reasons' => ['aroma']]]];
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, [...$data, 'overall' => 'suitable'])->assertOk();
        $this->assertDatabaseCount('fragrance_quiz_feedback', 1);
        $this->assertSame('suitable', FragranceQuizFeedback::first()->overall);
        $this->postJson($url, ['overall' => 'suitable', 'products' => [['product_id' => 999999, 'rating' => 'neutral', 'reasons' => []]]])->assertUnprocessable();
        $this->postJson($url, [...$data, 'text' => 'unallowed'])->assertUnprocessable();
        $this->withCookie(FragranceBrowser::COOKIE, str_repeat('b', 64))->postJson($url, $data)->assertNotFound();
    }

    public function test_browser_and_ip_rate_limits_and_csrf_are_enforced(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/fragrance-quiz', $this->answers())->assertStatus(303);
        }
        $this->postJson('/fragrance-quiz', $this->answers())->assertTooManyRequests();
        $this->app['env'] = 'local';
        $this->postJson('/fragrance-quiz', $this->answers())->assertStatus(419);
    }

    public function test_saving_failure_returns_retry_without_persisted_result_or_payload(): void
    {
        FragranceQuizResult::created(fn () => throw new \RuntimeException('Synthetic storage failure'));
        $this->postJson('/fragrance-quiz', $this->answers())->assertStatus(503)->assertSee('Coba lagi')->assertDontSee('Synthetic storage failure');
        $this->assertDatabaseCount('fragrance_quiz_results', 0);
    }

    public function test_admin_reports_require_existing_admin_and_profile_apply_is_actor_preview_bound(): void
    {
        $this->get('/admin/fragrance-preference')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/fragrance-preference')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/fragrance-preference')->assertOk();
        $this->post('/admin/fragrance-preference/apply', ['fingerprint' => str_repeat('0', 64)])->assertStatus(409);
        $this->post('/admin/fragrance-preference/preview')->assertRedirect();
        $this->post('/admin/fragrance-preference/apply', ['fingerprint' => session('fragrance_profile_preview.fingerprint')])->assertRedirect();
        $this->get('/admin/fragrance-preference/profiles/'.$this->product->id)->assertOk()->assertSee(route('admin.fragrance.review', $this->product->id));
        $profile = FragranceProfile::where('product_id', $this->product->id)->firstOrFail();
        $this->post('/admin/fragrance-preference/profiles/'.$this->product->id, ['revision' => $profile->revision, 'source' => $profile->source_fingerprint, 'changes' => '{"sweetness":"light"}', 'evidence' => 'Synthetic review basis for local test only.'])->assertRedirect(route('admin.fragrance.profile', $this->product->id));
    }

    public function test_feedback_limit_ip_limit_and_error_cache_headers(): void
    {
        $stored = $this->submit();
        $url = '/fragrance-quiz/results/'.$stored->id.'/feedback';
        for ($i = 0; $i < 20; $i++) {
            $this->postJson($url, ['overall' => 'invalid', 'products' => []])->assertUnprocessable();
        }
        $this->postJson($url, ['overall' => 'partly', 'products' => []])->assertTooManyRequests()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        for ($i = 0; $i < 29; $i++) {
            $this->withCookie(FragranceBrowser::COOKIE, hash('sha256', (string) $i))->postJson('/fragrance-quiz', [])->assertUnprocessable();
        }
        $this->withCookie(FragranceBrowser::COOKIE, str_repeat('c', 64))->postJson('/fragrance-quiz', [])->assertTooManyRequests();
    }

    public function test_feedback_storage_failure_rolls_back_and_cookie_is_secure_http_only_in_production(): void
    {
        $stored = $this->submit();
        FragranceQuizFeedback::created(fn () => throw new \RuntimeException('Synthetic feedback write failure'));
        $this->postJson('/fragrance-quiz/results/'.$stored->id.'/feedback', ['overall' => 'partly', 'products' => []])->assertStatus(503)->assertJsonPath('message', 'Feedback belum tersimpan. Silakan coba lagi.');
        $this->assertDatabaseCount('fragrance_quiz_feedback', 0);
        $this->app['env'] = 'production';
        $response = $this->withCookie(FragranceBrowser::COOKIE, 'invalid')->get('/fragrance-quiz');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === FragranceBrowser::COOKIE);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/fragrance-quiz', $cookie->getPath());
        $this->assertGreaterThan(time() + 6 * 86400, $cookie->getExpiresTime());
    }

    public function test_disabled_flag_retains_legacy_and_does_not_disclose_new_results(): void
    {
        $stored = $this->submit();
        config(['fragrance_preference.enabled' => false]);
        $this->get('/fragrance-quiz')->assertOk()->assertSee('1 dari 6')->assertDontSee('Pertanyaan 1 dari 10');
        $this->get('/fragrance-quiz/results/'.$stored->id)->assertNotFound();
        $this->post('/fragrance-quiz', ['activity' => 'office', 'time' => 'morning', 'intensity' => 'soft', 'scent' => 'fresh', 'mood' => 'clean', 'gender' => 'all'])->assertRedirect(route('quiz.result'));
    }
}
