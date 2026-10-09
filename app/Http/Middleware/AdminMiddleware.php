<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public const SESSION_AUTH_VERSION = 'admin_auth_version';

    public const SESSION_LAST_ACTIVITY = 'admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $lastActivity = (int) $request->session()->get(self::SESSION_LAST_ACTIVITY, 0);
        $idleSeconds = config('admin.idle_minutes') * 60;
        if ($lastActivity > 0 && now()->timestamp - $lastActivity > $idleSeconds) {
            return $this->endSession($request, 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
        }

        // Deactivation, role changes and password resets bump auth_version, revoking every older session.
        $version = $request->session()->get(self::SESSION_AUTH_VERSION);
        $current = (int) ($user->auth_version ?? 1);
        if ($user->is_active === false || ($version !== null && (int) $version !== $current)) {
            return $this->endSession($request, 'Akses akun Anda telah berubah. Silakan masuk kembali.');
        }

        if (! Gate::forUser($user)->allows('admin.access')) {
            abort(403);
        }

        $request->session()->put(self::SESSION_AUTH_VERSION, $current);
        $request->session()->put(self::SESSION_LAST_ACTIVITY, now()->timestamp);

        if ($user->must_change_password && ! $request->routeIs('admin.account.password*')) {
            return redirect()->route('admin.account.password')->with('error', 'Ganti password sementara Anda sebelum melanjutkan.');
        }

        return $next($request);
    }

    private function endSession(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('error', $message)->with('admin_clear_caches', true);
    }
}
