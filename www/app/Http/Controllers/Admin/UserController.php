<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderByDesc('created_at')->get();

        return view('admin.users', ['users' => $users]);
    }

    public function approve(User $user): RedirectResponse
    {
        $user->update(['approved_at' => now()]);

        return back()->with('flash', ['type' => 'success', 'message' => sprintf('%s onaylandı.', $user->email)]);
    }

    public function revoke(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Kendi hesabınızın onayını buradan kaldıramazsınız.');

        $user->update(['approved_at' => null]);

        return back()->with('flash', ['type' => 'success', 'message' => sprintf('%s onayı kaldırıldı.', $user->email)]);
    }

    public function toggleAdmin(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Kendi admin yetkinizi buradan değiştiremezsiniz.');

        $user->update(['is_admin' => !$user->is_admin]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => $user->is_admin
                ? sprintf('%s admin yapıldı.', $user->email)
                : sprintf('%s adminlikten çıkarıldı.', $user->email),
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Kendi hesabınızı buradan silemezsiniz.');

        $user->delete();

        return back()->with('flash', ['type' => 'success', 'message' => sprintf('%s silindi.', $user->email)]);
    }
}
