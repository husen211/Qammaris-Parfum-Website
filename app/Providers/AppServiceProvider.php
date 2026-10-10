<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\StoreInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ORD-04: guest checkout creates orders; a few honest retries per minute, no bulk creation from one client.
        RateLimiter::for('website-checkout', fn (Request $request) => [
            Limit::perMinute(6)->by('checkout-min|'.$request->ip()),
            Limit::perHour(30)->by('checkout-hour|'.$request->ip()),
        ]);

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

        view()->composer('components.footer', function ($view) {
            try {
                if (! Schema::hasTable('categories')) {
                    $view->with('footerCategories', collect());

                    return;
                }
            } catch (\Throwable $e) {
                $view->with('footerCategories', collect());

                return;
            }

            $footerCategories = cache()->remember('footer_categories', 3600, function () {
                return Category::active()->take(4)->get();
            });

            $view->with('footerCategories', $footerCategories);
        });
    }

    private function shareFallbackStoreData(): void
    {
        view()->share('storeInfo', new StoreInfo);
        view()->composer('components.footer', function ($view) {
            $view->with('footerCategories', collect());
        });
    }
}
