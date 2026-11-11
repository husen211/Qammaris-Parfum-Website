{{-- ORD-15: the automatic cancellation time of an unpaid order, so staff can remind the customer in time. --}}
@if ($order->payment_due_at && $received === 0 && $order->keep_status !== 'active')
    <p class="mt-1 text-xs {{ $order->payment_due_at->diffInHours(now(), true) < 6 ? 'font-semibold text-amber-900' : 'text-gray-600' }}" data-admin-payment-deadline>Batal otomatis bila belum dibayar sampai {{ $time($order->payment_due_at) }}.</p>
@endif
