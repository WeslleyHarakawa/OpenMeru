<?php

namespace App\Support;

use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Browser push notifications (standard Web Push with VAPID keys, which
 * Chrome delivers through Google's push service).
 */
class Push
{
    public static function publicKey(): ?string
    {
        return Setting::get('vapid_public');
    }

    public static function configured(): bool
    {
        return self::publicKey() && Setting::get('vapid_private');
    }

    /** @return array{publicKey: string, privateKey: string} */
    public static function generateKeys(): array
    {
        $keys = VAPID::createVapidKeys();
        Setting::put('vapid_public', $keys['publicKey']);
        Setting::put('vapid_private', $keys['privateKey']);

        return $keys;
    }

    public static function newMail(User $user, Message $message): void
    {
        $title = $message->from_name ?: ($message->from_email ?: 'OpenMeru Mail');
        self::send($user, [
            'title' => $title,
            'body' => $message->subject ?: __('noSubject', [], Locales::supported($user->locale) ? $user->locale : 'en'),
            'url' => (parse_url(route('login'), PHP_URL_PATH) ?: '/').'?open='.$message->id,
            'tag' => 'mail-'.$message->id,
        ]);
    }

    public static function send(User $user, array $payload): int
    {
        if (! self::configured()) {
            return 0;
        }
        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $push = new WebPush(['VAPID' => [
                'subject' => 'mailto:'.Setting::get('system_from', 'hello@'.config('inbound.domain')),
                'publicKey' => Setting::get('vapid_public'),
                'privateKey' => Setting::get('vapid_private'),
            ]], ['TTL' => 86400]);

            foreach ($subscriptions as $sub) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                    'contentEncoding' => 'aes128gcm',
                ]), json_encode($payload));
            }

            $sent = 0;
            foreach ($push->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    $user->pushSubscriptions()->where('endpoint', $report->getEndpoint())->delete();
                }
            }

            return $sent;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }
}
