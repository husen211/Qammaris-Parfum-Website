<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\StoreInfo;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo();
        });

        view()->share('storeInfo', $storeInfo);

        view()->composer('components.footer', function ($view) {
            $footerCategories = cache()->remember('footer_categories', 3600, function () {
                return Category::query()->take(4)->get();
            });

            $view->with('footerCategories', $footerCategories);
        });
    }
}
