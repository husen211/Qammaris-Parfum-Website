@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    
    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Welcome back, Admin!</h1>
            <p class="text-gray-500 text-sm mt-1">Here's what's happening with your store today.</p>
        </div>
        <div class="text-right">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400">Current Date</p>
            <p class="text-lg font-medium text-gray-900">{{ date('l, d M Y') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Total Products</h3>
                <div class="p-2 bg-blue-50 rounded text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <div class="flex items-baseline">
                <span class="text-3xl font-bold text-gray-900">
                    {{ $productCount }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Items</span>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-50">
                <a href="{{ route('admin.products.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 flex items-center">
                    Manage Products &rarr;
                </a>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Brands</h3>
                <div class="p-2 bg-purple-50 rounded text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
            </div>
            <div class="flex items-baseline">
                <span class="text-3xl font-bold text-gray-900">
                    {{ $brandCount }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Brands Active</span>
            </div>
             <div class="mt-4 pt-4 border-t border-gray-50">
                <span class="text-xs text-gray-400">Manage via Database</span>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
             <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Categories</h3>
                <div class="p-2 bg-green-50 rounded text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </div>
            </div>
            <div class="flex items-baseline">
                <span class="text-3xl font-bold text-gray-900">
                    {{ $categoryCount }}
                </span>
                <span class="ml-2 text-sm text-gray-500">Categories</span>
            </div>
             <div class="mt-4 pt-4 border-t border-gray-50">
                <span class="text-xs text-gray-400">Manage via Database</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="{{ route('admin.products.create') }}" class="block w-full text-center py-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-black hover:bg-gray-50 transition-all text-sm font-medium text-gray-600 hover:text-black">
                    + Add New Product
                </a>
                <a href="{{ route('home') }}" target="_blank" class="block w-full text-center py-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all text-sm font-medium text-gray-600">
                    View Live Website
                </a>
            </div>
        </div>
        
        <div class="bg-gray-900 text-white p-6 rounded-lg shadow-lg flex flex-col justify-center text-center">
            <h3 class="text-xl font-mayluxa mb-2">Qammaris Perfumes</h3>
            <p class="text-gray-400 text-sm mb-6">You are logged in as Super Administrator.</p>
            <div class="text-xs font-mono bg-gray-800 py-2 rounded">
                Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
            </div>
        </div>
    </div>

</div>
@endsection
