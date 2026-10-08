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
        // Staff Order has no catalog dashboard; its home is the order list (ORD-02a).
        if (! auth()->user()->can('dashboard.view')) {
            return redirect()->route('admin.orders.index');
        }

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
