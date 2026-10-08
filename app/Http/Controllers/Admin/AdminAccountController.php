<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ManageAdminUser;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Any active admin account: change own password (mandatory after a temporary password). */
class AdminAccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.account.password', ['user' => $request->user()]);
    }

    public function update(Request $request, ManageAdminUser $users)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) use ($user) {
                if (! Hash::check((string) $value, $user->password)) {
                    $fail('Password saat ini salah.');
                }
            }],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ], ['current_password' => 'password saat ini', 'password' => 'password baru']);

        $version = $users->changeOwnPassword($user, (string) $request->input('password'));
        $request->session()->regenerate();
        $request->session()->put(AdminMiddleware::SESSION_AUTH_VERSION, $version);

        return redirect()->route($request->user()->can('dashboard.view') ? 'admin.dashboard' : 'admin.orders.index')
            ->with('success', 'Password berhasil diganti.');
    }
}
