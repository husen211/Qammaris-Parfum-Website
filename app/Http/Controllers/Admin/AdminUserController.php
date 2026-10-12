<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ManageAdminUser;
use App\Exceptions\AdminUserRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Models\User;
use App\Support\SearchMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pengguna & Role — Super Admin only (route `can:users.manage`, request and action re-check it). */
class AdminUserController extends Controller
{
    public function __construct(private readonly ManageAdminUser $users) {}

    public function index(Request $request)
    {
        $search = SearchMatcher::term($request->query('search'));
        $query = User::query()->whereIn('role', User::ADMIN_ROLES)->orderByDesc('is_active')->orderBy('name');
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('username', 'like', $like));
        }

        return view('admin.users.index', [
            'users' => $query->paginate(30)->withQueryString(),
            'search' => $search,
            'recent' => $this->changes()->limit(15)->get(),
            'names' => User::query()->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => null]);
    }

    public function store(AdminUserRequest $request)
    {
        try {
            [$user, $password] = $this->users->create($request->user(), $request->validated());
        } catch (AdminUserRejected $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Akun dibuat. Berikan password sementara secara langsung; pengguna wajib menggantinya saat login.')
            ->with('temporary_password', $password);
    }

    public function show(User $user)
    {
        abort_unless($user->hasAdminRole(), 404);

        return view('admin.users.show', [
            'user' => $user,
            'changes' => $this->changes()->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('actor_id', $user->id))->limit(50)->get(),
            'orderEvents' => DB::table('online_order_events')->join('online_orders', 'online_orders.id', '=', 'online_order_events.online_order_id')
                ->where('online_order_events.actor_user_id', $user->id)->orderByDesc('online_order_events.id')->limit(20)
                ->get(['online_orders.id as order_id', 'online_orders.code', 'online_order_events.kind', 'online_order_events.stage', 'online_order_events.created_at']),
            'productChanges' => DB::table('product_admin_changes')->leftJoin('products', 'products.id', '=', 'product_admin_changes.product_id')
                ->where('product_admin_changes.actor_id', $user->id)->orderByDesc('product_admin_changes.id')->limit(20)
                ->get(['product_admin_changes.product_id', 'products.name', 'product_admin_changes.action', 'product_admin_changes.created_at']),
            'names' => User::query()->pluck('name', 'id'),
        ]);
    }

    public function edit(User $user)
    {
        abort_unless($user->hasAdminRole(), 404);

        return view('admin.users.form', ['user' => $user]);
    }

    public function update(AdminUserRequest $request, User $user)
    {
        abort_unless($user->hasAdminRole(), 404);

        return $this->attempt($user, fn () => $this->users->update($request->user(), $user, $request->validated()), 'Akun diperbarui.');
    }

    public function status(Request $request, User $user)
    {
        abort_unless($user->hasAdminRole() && $request->user()->can('users.manage'), 404);
        $data = $request->validate(['active' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:200']]);
        $active = (bool) $data['active'];

        return $this->attempt($user, fn () => $this->users->setActive($request->user(), $user, $active, $data['note'] ?? null),
            $active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan; semua sesi akun ini berakhir.');
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_unless($user->hasAdminRole() && $request->user()->can('users.manage'), 404);
        try {
            $password = $this->users->resetPassword($request->user(), $user);
        } catch (AdminUserRejected $error) {
            return redirect()->route('admin.users.show', $user)->with('error', $error->getMessage());
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Password direset dan semua sesi akun ini berakhir.')
            ->with('temporary_password', $password);
    }

    private function changes()
    {
        return DB::table('user_admin_changes')->orderByDesc('id');
    }

    private function attempt(User $user, \Closure $change, string $success)
    {
        try {
            $change();
        } catch (AdminUserRejected $error) {
            return redirect()->route('admin.users.show', $user)->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('admin.users.show', $user)->with('success', $success);
    }
}
