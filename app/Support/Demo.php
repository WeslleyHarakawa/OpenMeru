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
        if (! $newest || now()->diffInHours($newest, true) > 24) {
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
        $items = [
            ['inbox', 'OpenMeru Team', 'hello@'.$domain, 'Welcome to OpenMeru Mail 👋', "Hi!\n\nThis is the OpenMeru demo mailbox. Look around: folders, search, the rich editor, settings, themes and the 10 languages.\n\nIn the demo nothing is sent or saved, so feel free to click anything.\n\nTo run OpenMeru on your own domain, follow the guide at https://{$domain}/#install.\n\nThe OpenMeru team", 0.2, false, true],
            ['inbox', 'Maria Silva', self::address('maria.silva'), 'Q4 planning: notes from today', "Hi,\n\nHere are the notes from today's call:\n\n1. Launch the new landing page next week.\n2. Move the newsletter to the 1st of each month.\n3. Review the support inbox every morning.\n\nLet me know if I missed anything.\n\nMaria", 1.5, false, false],
            ['inbox', 'Cloudflare', 'noreply@notify.cloudflare.com', 'Email Routing is active for '.$domain, "Email Routing is now active.\n\nCatch-all address: Send to a Worker (openmeru-mail-inbound).\n\nMessages to any address on your domain are now handled by your Worker.", 5, true, false],
            ['inbox', 'John Carter', 'john.carter@example.com', 'Re: Proposal for the website redesign', "Thanks for the proposal, it looks great.\n\nCan we schedule a quick call on Thursday to go over the timeline?\n\nBest,\nJohn", 9, false, true],
            ['inbox', 'Brevo', 'contact@brevo.com', 'Your domain '.$domain.' is authenticated', "Good news! The DNS records for {$domain} were verified.\n\nDKIM: verified\nDMARC: verified\nBrevo code: verified\n\nYou can now send email from any address on this domain.", 26, true, false],
            ['inbox', 'Ana Costa', self::address('ana.costa'), 'Photos from the event 📸', "Hi!\n\nThe photos from Saturday's event are ready. I'll share the full album later today.\n\nAna", 50, true, false],
            ['inbox', 'Li Wei', 'li.wei@example.org', 'Invoice #2026-104', "Hello,\n\nPlease find the invoice details below:\n\nInvoice: #2026-104\nAmount: €480.00\nDue: in 15 days\n\nThank you for your business.\nLi Wei", 74, true, false],
            ['sent', $user->name, $me, 'Meeting on Thursday', "Hi John,\n\nThursday at 10:00 works for me. I'll send the invite.\n\nSee you then!", 8, true, false, ['john.carter@example.com']],
            ['sent', $user->name, $me, 'Welcome aboard, Sophie!', "Hi Sophie,\n\nWelcome to the team! Your mailbox is ready: sophie.martin@{$domain}.\n\nLet me know if you need anything.", 30, true, false, [self::address('sophie.martin')]],
            ['drafts', $user->name, $me, 'Ideas for the newsletter', "- Feature of the month: push notifications\n- Tip: auto-forwarding\n- Community showcase", 3, true, false, []],
            ['archive', 'Sophie Martin', self::address('sophie.martin'), 'Thanks for the welcome!', "Thank you! Happy to be here.\n\nSophie", 29, true, false],
            ['spam', 'Lottery Winner Dept.', 'win@prize-claims.example', 'You have WON €1,000,000!!!', 'Click here to claim your prize now.', 40, false, false],
        ];

        foreach ($items as $row) {
            [$folder, $name, $from, $subject, $body, $hoursAgo, $read, $starred] = $row;
            $to = $row[8] ?? [$me];
            $message = new Message;
            $message->user_id = $user->id;
            $message->forceFill([
                'folder' => $folder,
                'message_id' => Str::uuid().'@demo.'.$domain,
                'from_name' => $name,
                'from_email' => $from,
                'to' => $to,
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
