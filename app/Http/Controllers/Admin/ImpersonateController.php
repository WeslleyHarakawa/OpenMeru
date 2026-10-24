<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets an admin open a mailbox as its owner. The admin's id stays in the
 * session so the mailbox page can show a "back to admin" bar.
 */
class ImpersonateController extends Controller
{
    public function start(Request $request, User $account): RedirectResponse
    {
        $admin = $request->user();
        abort_if($account->is($admin), 422, __('admin.alreadyYou'));
        abort_unless($account->is_active, 422, __('admin.accountDisabled'));

        $request->session()->put('impersonator', $admin->id);
        $request->session()->forget('admin_verified');
        Auth::login($account);

        return redirect()->route('login');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator');
        $admin = $adminId ? User::where('id', $adminId)->where('is_admin', true)->where('is_active', true)->first() : null;
        if (! $admin) {
            return redirect()->route('login');
        }

        Auth::login($admin);
        $request->session()->put('admin_verified', $admin->id);

        return redirect()->route('admin.accounts');
    }
}
