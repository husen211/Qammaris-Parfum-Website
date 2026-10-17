<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminPush;
use App\Models\AdminPushSubscription;
use App\Support\AdminPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ORD-09: a browser of the signed-in order team member turns admin push notifications on or off. The same browser
 * re-subscribing (or another account signing in on a shared device) moves the endpoint to the current account.
 */
class AdminPushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(AdminPush::enabled(), 404);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+=*$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+=*$/'],
        ]);
        AdminPushSubscription::query()->updateOrCreate(['endpoint_hash' => AdminPushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'user_agent' => Str::limit((string) $request->userAgent(), 157),
        ]);

        return response()->json(['subscribed' => true]);
    }

    /** "Kirim tes": a push to this account's own devices, to check the setup. */
    public function test(Request $request): JsonResponse
    {
        abort_unless(AdminPush::enabled(), 404);
        SendAdminPush::dispatch([
            'title' => 'Tes notifikasi Qammaris',
            'body' => 'Notifikasi pesanan aktif untuk '.$request->user()->name.'.',
            'url' => route('admin.orders.index', [], false),
            'tag' => 'test-'.$request->user()->id,
        ], $request->user()->id);

        return response()->json(['queued' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        AdminPushSubscription::query()->where('endpoint_hash', AdminPushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['subscribed' => false]);
    }
}
