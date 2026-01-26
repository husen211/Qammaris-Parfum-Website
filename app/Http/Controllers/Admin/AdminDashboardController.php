<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $counts = cache()->remember('admin_dashboard_counts', 600, function () {
            return [
                'productCount' => Product::count(),
                'brandCount' => Brand::count(),
                'categoryCount' => Category::count(),
            ];
        });

        return view('admin.dashboard', $counts);
    }
}
