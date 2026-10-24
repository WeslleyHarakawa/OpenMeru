<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Opens the public demo: /demo for the mailbox, /demo/admin for the admin panel. */
class DemoController extends Controller
{
    public function mailbox(Request $request): RedirectResponse
    {
        $this->signIn($request, Demo::mailbox());

        return redirect()->route('login');
    }

    public function admin(Request $request): RedirectResponse
    {
        $admin = Demo::admin();
        $this->signIn($request, $admin);
        // The demo admin skips the emailed sign-in code.
        $request->session()->put('admin_verified', $admin->id);

        return redirect()->route('admin.accounts');
    }

    private function signIn(Request $request, $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('impersonator');
    }
}
