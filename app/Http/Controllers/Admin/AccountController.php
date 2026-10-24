<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountController extends Controller
{
    public static function domain(): string
    {
        return (string) config('inbound.domain');
    }

    public function index(Request $request): View|Response
    {
        $q = trim((string) $request->query('q'));
        $accounts = User::query()
            ->withCount('messages')
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('folder', 'inbox')->where('is_read', false)])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('email')
            ->paginate(50)
            ->withPath('/admin')
            ->withQueryString();

        // Search box and page links: only the list comes back.
        if ($request->ajax()) {
            return response()->view('admin.account-list', ['accounts' => $accounts, 'q' => $q])
                ->header('X-Total-Count', (string) $accounts->total());
        }

        return view('admin.accounts', ['accounts' => $accounts, 'domain' => self::domain(), 'q' => $q]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['local' => mb_strtolower(trim((string) $request->input('local')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'local' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);
        $email = $data['local'].'@'.self::domain();
        $request->merge(['email' => $email]);
        $request->validate(['email' => ['email', Rule::unique('users', 'email')]]);

        User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
            'recovery_email' => $data['recovery_email'] ?? null,
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => true,
        ]);

        return redirect()->route('admin.accounts')->with('status', __('admin.created', ['email' => $email]));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8', 'max:200'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
            'signature' => ['nullable', 'string', 'max:2000'],
            'is_admin' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $isSelf = $account->is($request->user());
        $account->fill([
            'name' => $data['name'],
            'recovery_email' => $data['recovery_email'] ?? null,
            'signature' => $data['signature'] ?? null,
            // An admin cannot lock themselves out of /admin.
            'is_admin' => $isSelf ? true : $request->boolean('is_admin'),
            'is_active' => $isSelf ? true : $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $account->password = $data['password'];
        }
        $account->save();

        if (! $account->is_active || ! empty($data['password'])) {
            DB::table('sessions')->where('user_id', $account->id)->delete();
        }

        return redirect()->back()->with('status', __('admin.updated', ['email' => $account->email]));
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_if($account->is($request->user()), 422, __('admin.cannotDeleteSelf'));

        Storage::disk('local')->deleteDirectory('attachments/'.$account->id);
        Storage::disk('local')->deleteDirectory('raw/'.$account->id);
        DB::table('sessions')->where('user_id', $account->id)->delete();
        $email = $account->email;
        $account->delete();

        return redirect()->back()->with('status', __('admin.deletedMsg', ['email' => $email]));
    }
}
