<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Updates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    /** Current state of the background update, polled by the admin banner. */
    public function status(): JsonResponse
    {
        return response()->json(Updates::status() + ['check' => Updates::check(), 'can_self_update' => Updates::canSelfUpdate()]);
    }

    public function check(): RedirectResponse
    {
        $check = Updates::check(force: true);

        return redirect()->back()->with($check['error'] ? 'error' : 'status', $check['error']
            ? __('admin.updateCheckFailed', ['error' => $check['error']])
            : ($check['available'] ? __('admin.updateFound', ['version' => $check['latest']]) : __('admin.upToDate', ['version' => $check['current']])));
    }

    public function start(): JsonResponse
    {
        if (! Updates::canSelfUpdate()) {
            return response()->json(['message' => 'manual', 'command' => Updates::installCommand()], 422);
        }
        if (Updates::isRunning()) {
            return response()->json(['message' => 'running'], 409);
        }

        return response()->json(['started' => Updates::start()]);
    }

    public function dismiss(): RedirectResponse
    {
        Updates::clearStatus();

        return redirect()->back();
    }
}
