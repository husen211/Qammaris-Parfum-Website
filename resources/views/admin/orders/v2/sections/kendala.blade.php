@use('App\Models\OnlineOrderIssue')
{{-- ORD-03 "Ada masalah": open issues are always visible; recording a new one is behind a disclosure. --}}
<details id="kendala" class="{{ $card }}" @if ($isOpen('kendala', $openIssues > 0)) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
        <span><span class="block text-base font-semibold text-gray-900">Ada masalah</span><span @class(['block text-sm', 'text-red-700' => $openIssues > 0, 'text-gray-600' => $openIssues === 0])>{{ $openIssues > 0 ? $openIssues.' kendala terbuka' : 'Stok kurang, kurir, customer, atau lainnya' }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'kendala'])
    @if ($order->issues->isNotEmpty())
        <ul class="mt-3 space-y-2">
            @foreach ($order->issues as $issue)
                <li class="rounded-lg border p-3 text-sm {{ $issue->status === 'open' ? 'border-red-200 bg-red-50' : 'border-gray-200' }}">
                    <p class="font-semibold text-gray-900">{{ OnlineOrderIssue::TYPES[$issue->type] }} <span class="font-normal text-gray-500">· {{ $issue->opened_by_name }} · {{ $time($issue->created_at) }}</span></p>
                    <p class="mt-1 text-gray-800">{{ $issue->note }}@if ($issue->reported_quantity !== null) (ada {{ $issue->reported_quantity }})@endif</p>
                    @if ($issue->status === 'resolved')
                        <p class="mt-1 text-green-800">Selesai {{ $time($issue->resolved_at) }}{{ $issue->resolution_note ? ': '.$issue->resolution_note : '' }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.orders.v2.issues.resolve', [$order, $issue->public_id]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                            @csrf {!! $hidden('kendala') !!}
                            <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Penyelesaian <span class="font-normal text-gray-500">(opsional)</span><input name="note" maxlength="500" class="{{ $input }}"></label>
                            <button class="{{ $secondary }}" data-busy-label="…">Tandai selesai</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($order->lifecycle !== 'cancelled' && config('orders_api.enabled') && ! config('orders_api.website_issues'))
        <p class="mt-3 text-sm text-gray-600" data-issue-app-only>Selama integrasi Qammaris App aktif, kendala baru dicatat dari Qammaris App. Kendala yang ada tetap bisa ditandai selesai di sini.</p>
    @elseif ($order->lifecycle !== 'cancelled')
        <form method="POST" action="{{ route('admin.orders.v2.issues', $order) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf {!! $hidden('kendala') !!}
            <label class="text-sm font-medium text-gray-700">Jenis
                <select name="type" required class="{{ $input }}">@foreach (OnlineOrderIssue::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select>
            </label>
            <label class="text-sm font-medium text-gray-700">Barang <span class="font-normal text-gray-500">(opsional)</span>
                <select name="line_id" class="{{ $input }}"><option value="">—</option>@foreach ($order->items as $item)<option value="{{ $item->line_id }}">{{ $item->label() }} (dipesan {{ $item->quantity }})</option>@endforeach</select>
            </label>
            <label class="text-sm font-medium text-gray-700 sm:col-span-2">Keterangan<input name="note" required maxlength="500" value="{{ old('note') }}" class="{{ $input }}"></label>
            <label class="text-sm font-medium text-gray-700">Jumlah tersedia <span class="font-normal text-gray-500">(opsional)</span><input name="reported_quantity" type="number" min="0" max="99" inputmode="numeric" class="{{ $input }}"></label>
            <div class="flex items-end"><button class="{{ $primary }}" data-busy-label="Mencatat…">Catat kendala</button></div>
        </form>
    @endif
</details>
