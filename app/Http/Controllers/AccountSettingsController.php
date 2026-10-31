<?php

namespace App\Http\Controllers;

use App\Support\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The signed-in person's own settings: profile, password, forwarding, push. */
class AccountSettingsController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'signature' => ['nullable', 'string', 'max:2000'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
        ]);
        $request->user()->forceFill($data)->save();

        return response()->json(['ok' => true]);
    }

    public function page(Request $request): View
    {
        return view('mail.settings', ['user' => $request->user()]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072']]);
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $path = $request->file('avatar')->store('avatars', 'local');
        $user->forceFill(['avatar_path' => $path])->save();

        return response()->json(['ok' => true, 'url' => route('account.avatar').'?v='.time()]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return response()->json(['ok' => true]);
    }

    public function avatar(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** The person deletes their own mailbox, with every message, after typing the password. */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'wrongPassword']);
        }
        if ($user->is_admin) {
            throw ValidationException::withMessages(['password' => 'adminCannotDelete']);
        }

        Storage::disk('local')->deleteDirectory('attachments/'.$user->id);
        Storage::disk('local')->deleteDirectory('raw/'.$user->id);
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => route('login')]);
    }

    public function password(Request $request): JsonResponse
    {
        // The person is signed in (password or admin-set one they may not know): no current password asked.
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        $user = $request->user();

        $user->forceFill(['password' => $data['password']])->save();
        // Sign out every other session that used the old password.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        return response()->json(['ok' => true]);
    }

    public function forwarding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'forward_to' => ['nullable', 'email', 'max:255', 'not_regex:/@'.preg_quote((string) config('inbound.domain'), '/').'$/i'],
            'forward_keep' => ['boolean'],
        ]);
        $request->user()->forceFill([
            'forward_to' => filled($data['forward_to'] ?? null) ? mb_strtolower(trim($data['forward_to'])) : null,
            'forward_keep' => $data['forward_keep'] ?? true,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = $request->user()->messages()
            ->where('folder', 'inbox')->where('is_read', false)
            ->orderByDesc('date')->limit(8)->get()
            ->map->summary();

        return response()->json([
            'items' => $items,
            'unread' => $request->user()->messages()->where('folder', 'inbox')->where('is_read', false)->count(),
            'push_key' => Push::publicKey(),
            'account' => $request->user()->email,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);
        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->user()->pushSubscriptions()->where('endpoint', (string) $request->input('endpoint'))->delete();

        return response()->json(['ok' => true]);
    }

    public function testPush(Request $request): JsonResponse
    {
        $sent = Push::send($request->user(), [
            'title' => 'OpenMeru Mail',
            'body' => 'As notificações estão funcionando.',
            'url' => parse_url(route('login'), PHP_URL_PATH) ?: '/',
            'tag' => 'test',
        ]);

        return response()->json(['sent' => $sent]);
    }
}
