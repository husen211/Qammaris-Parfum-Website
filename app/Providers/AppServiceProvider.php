<?php

namespace App\Providers;

use App\Models\StoreInfo;
use App\Support\CatalogCollection;
use App\Support\RetryingMySqlConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('db.connector.mysql', RetryingMySqlConnector::class);
    }

    public function boot(): void
    {
        // ORD-04: guest checkout creates orders; a few honest retries per minute, no bulk creation from one client.
        RateLimiter::for('website-checkout', fn (Request $request) => [
            Limit::perMinute(6)->by('checkout-min|'.$request->ip()),
            Limit::perHour(30)->by('checkout-hour|'.$request->ip()),
        ]);

        foreach (['fragrance-submit' => 10, 'fragrance-feedback' => 20] as $name => $limit) {
            RateLimiter::for($name, fn ($request) => [Limit::perMinute($limit)->by($name.':browser:'.$request->attributes->get('preference_browser_hash')), Limit::perMinute($limit * 3)->by($name.':ip:'.hash('sha256', $request->ip()))]);
        }
        RateLimiter::for('blog-automation', fn ($request) => Limit::perMinute(60)->by('blog-actor:'.$request->user()->id));
        RateLimiter::for('blog-automation-media', fn ($request) => Limit::perMinute(10)->by('blog-media-actor:'.$request->user()->id));
        // SEO-03: footer links to the permanent collection and brand pages (brand availability cached for an hour).
        view()->composer('components.footer', function ($view) {
            try {
                $available = Schema::hasTable('brands');
            } catch (\Throwable $e) {
                $available = false;
            }

            $view->with('footerCollections', $available ? CatalogCollection::links() : collect());
        });

        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return;
        }

        try {
            if (! Schema::hasTable('store_info')) {
                $this->shareFallbackStoreData();

                return;
            }
        } catch (\Throwable $e) {
            $this->shareFallbackStoreData();

            return;
        }

        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo;
        });

        view()->share('storeInfo', $storeInfo);
    }

    private function shareFallbackStoreData(): void
    {
        view()->share('storeInfo', new StoreInfo);
    }
}
