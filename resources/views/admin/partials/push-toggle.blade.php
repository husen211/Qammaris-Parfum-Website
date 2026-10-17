{{-- ORD-09: turn on push notifications for new website orders on this device. Filled in by resources/js/admin-push.js. --}}
@can('orders.manage')
    @if (\App\Support\AdminPush::enabled() && config('admin.pwa_enabled'))
        <div data-admin-push data-csrf="{{ csrf_token() }}" data-test-url="{{ route('admin.orders.push-subscriptions.test', [], false) }}" hidden class="{{ $class ?? '' }} rounded-lg border border-gray-200 bg-white p-3">
            <p class="text-sm font-semibold text-gray-900">Notifikasi pesanan</p>
            <p data-admin-push-status role="status" class="mt-0.5 text-xs text-gray-600"></p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" data-admin-push-toggle class="inline-flex min-h-10 items-center rounded-lg bg-black px-3 text-xs font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400"></button>
                <button type="button" data-admin-push-test hidden class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-800 hover:bg-gray-50">Kirim tes</button>
            </div>
        </div>
    @endif
@endcan
