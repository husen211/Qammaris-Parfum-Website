<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AdminMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Tampilkan Form Login
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Sign in with email or username (Owner D3). No remember-me (D7); inactive accounts get the same
     * generic error as a wrong password so account status is not disclosed.
     */
    public function login(Request $request)
    {
        // Older clients/tests still post "email"; the form now posts "login".
        $field = $request->has('login') ? 'login' : 'email';
        $request->validate([
            $field => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $identifier = trim((string) $request->input($field));
        $column = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $value = $column === 'username' ? Str::lower($identifier) : $identifier;

        $throttleKey = $this->throttleKey($request, $identifier);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                $field => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
            ])->onlyInput($field);
        }

        if (Auth::attempt([$column => $value, 'password' => $request->input('password'), 'is_active' => true], false)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            $request->session()->put(AdminMiddleware::SESSION_AUTH_VERSION, (int) ($user->auth_version ?? 1));
            $request->session()->put(AdminMiddleware::SESSION_LAST_ACTIVITY, now()->timestamp);

            if (Gate::forUser($user)->allows('dashboard.view')) {
                return redirect()->intended(route('admin.dashboard'));
            }
            if (Gate::forUser($user)->allows('orders.manage')) {
                return redirect()->intended(route('admin.orders.index'));
            }

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            $field => 'Email/username atau password salah.',
        ])->onlyInput($field);
    }

    // Proses Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function throttleKey(Request $request, string $identifier): string
    {
        return Str::transliterate(Str::lower($identifier)).'|'.$request->ip();
    }
}
