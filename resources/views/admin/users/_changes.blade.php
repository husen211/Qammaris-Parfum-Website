@php
    $actions = [
        'created' => 'membuat akun', 'updated' => 'mengubah akun', 'activated' => 'mengaktifkan akun', 'deactivated' => 'menonaktifkan akun',
        'password_reset' => 'mereset password', 'password_changed' => 'mengganti password sendiri', 'bootstrap_super_admin' => 'bootstrap Super Admin',
    ];
    $fieldLabels = ['name' => 'nama', 'email' => 'email', 'username' => 'username', 'role' => 'role', 'is_active' => 'status', 'must_change_password' => 'wajib ganti password', 'password' => 'password'];
@endphp
@if ($changes->isEmpty())
    <p class="mt-3 text-sm text-gray-500">Belum ada perubahan tercatat.</p>
@else
    <ol class="mt-3 space-y-2 text-sm">
        @foreach ($changes as $change)
            @php $fields = json_decode($change->changed_fields, true) ?: []; @endphp
            <li class="flex flex-wrap gap-x-2 text-gray-700">
                <span class="tabular-nums text-gray-500">{{ \Illuminate\Support\Carbon::parse($change->created_at)->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') }}</span>
                <span class="font-medium">{{ $change->actor_id ? ($names[$change->actor_id] ?? '#'.$change->actor_id) : 'Sistem (perintah server)' }}</span>
                <span>{{ $actions[$change->action] ?? $change->action }}</span>
                <span class="font-medium">{{ $names[$change->user_id] ?? '#'.$change->user_id }}</span>
                @if ($fields)<span class="text-gray-500">({{ collect($fields)->map(fn ($field) => $fieldLabels[$field] ?? $field)->implode(', ') }})</span>@endif
                @if ($change->note)<span class="text-gray-500">— {{ $change->note }}</span>@endif
            </li>
        @endforeach
    </ol>
@endif
