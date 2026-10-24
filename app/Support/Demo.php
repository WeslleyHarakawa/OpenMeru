<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Public demo at /demo (a mailbox) and /demo/admin (the admin panel).
 * Demo users are read-only (see DemoReadOnly), never receive real mail and
 * cannot sign in with a password. The sample mail is rebuilt once a day so
 * the dates stay fresh.
 */
class Demo
{
    /** Extra mailboxes that fill the demo admin list. */
    private const PEOPLE = [
        ['maria.silva', 'Maria Silva', true],
        ['john.carter', 'John Carter', true],
        ['ana.costa', 'Ana Costa', true],
        ['li.wei', 'Li Wei', true],
        ['sophie.martin', 'Sophie Martin', true],
        ['lucas.meyer', 'Lucas Meyer', false],
    ];

    public static function mailbox(): User
    {
        self::ensure();

        return User::where('email', self::address('demo'))->firstOrFail();
    }

    public static function admin(): User
    {
        self::ensure();

        return User::where('email', self::address('demo.admin'))->firstOrFail();
    }

    public static function address(string $local): string
    {
        return $local.'@'.config('inbound.domain');
    }

    private static function ensure(): void
    {
        $mailbox = self::user('demo', 'Demo User', false, true);
        self::user('demo.admin', 'Demo Admin', true, true);
        foreach (self::PEOPLE as [$local, $name, $active]) {
            self::user($local, $name, false, $active);
        }

        $newest = $mailbox->messages()->max('date');
        $current = $mailbox->messages()->where('message_id', 'like', 'demo-%')->exists();
        if (! $newest || ! $current || now()->diffInHours($newest, true) > 24) {
            $mailbox->messages()->delete();
            self::seed($mailbox);
        }
    }

    private static function user(string $local, string $name, bool $admin, bool $active): User
    {
        $user = User::firstOrNew(['email' => self::address($local)]);
        if (! $user->exists) {
            $user->forceFill([
                'name' => $name,
                'password' => Str::random(48),
                'is_admin' => $admin,
                'is_demo' => true,
                'is_active' => $active,
                'last_login_at' => now()->subDays(random_int(0, 20)),
            ])->save();
        } elseif (! $user->is_demo) {
            // Never take over a real mailbox that happens to use a demo address.
            abort(503, 'Demo unavailable');
        }

        return $user;
    }

    private static function seed(User $user): void
    {
        $me = $user->email;
        $domain = config('inbound.domain');
        // [key, folder, sender name, sender address, hours ago, read, starred, recipients]
        $items = [
            ['welcome', 'inbox', 'OpenMeru Team', 'hello@'.$domain, 0.2, false, true],
            ['planning', 'inbox', 'Maria Silva', self::address('maria.silva'), 1.5, false, false],
            ['cloudflare', 'inbox', 'Cloudflare', 'noreply@notify.cloudflare.com', 5, true, false],
            ['proposal', 'inbox', 'John Carter', 'john.carter@example.com', 9, false, true],
            ['brevo', 'inbox', 'Brevo', 'contact@brevo.com', 26, true, false],
            ['photos', 'inbox', 'Ana Costa', self::address('ana.costa'), 50, true, false],
            ['invoice', 'inbox', 'Li Wei', 'li.wei@example.org', 74, true, false],
            ['meeting', 'sent', $user->name, $me, 8, true, false, ['john.carter@example.com']],
            ['sophie', 'sent', $user->name, $me, 30, true, false, [self::address('sophie.martin')]],
            ['ideas', 'drafts', $user->name, $me, 3, true, false, []],
            ['thanks', 'archive', 'Sophie Martin', self::address('sophie.martin'), 29, true, false],
            ['spam', 'spam', 'Lottery Winner Dept.', 'win@prize-claims.example', 40, false, false],
        ];

        foreach ($items as $row) {
            [$key, $folder, $name, $from, $hoursAgo, $read, $starred] = $row;
            [$subject, $body] = DemoContent::get($key, 'en');
            $message = new Message;
            $message->user_id = $user->id;
            $message->forceFill([
                'folder' => $folder,
                // The key lets Message show this text in the visitor's language.
                'message_id' => 'demo-'.$key.'-'.Str::lower(Str::random(24)).'@demo.'.$domain,
                'from_name' => $name,
                'from_email' => $from,
                'to' => $row[7] ?? [$me],
                'cc' => [],
                'bcc' => [],
                'subject' => $subject,
                'text_body' => $body,
                'snippet' => Message::makeSnippet($body, null),
                'is_read' => $read,
                'is_starred' => $starred,
                'date' => now()->subMinutes((int) round($hoursAgo * 60)),
            ])->save();
        }
    }
}
