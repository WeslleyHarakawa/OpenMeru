<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail from a mailbox through the SMTP relay saved in the admin
 * settings (Brevo), and files a copy in the sender's Sent folder.
 */
class Outbox
{
    /** SMTP relays the admin can pick in Settings → Sending; "custom" is any other SMTP server. */
    public const PROVIDERS = [
        'brevo' => ['name' => 'Brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'api' => true],
        'smtp2go' => ['name' => 'SMTP2GO', 'host' => 'mail.smtp2go.com', 'port' => 587, 'api' => false],
        'mailgun' => ['name' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'api' => false],
        'postmark' => ['name' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'api' => false],
        'sendgrid' => ['name' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'login' => 'apikey', 'api' => false],
        'ses' => ['name' => 'Amazon SES', 'host' => 'email-smtp.eu-west-1.amazonaws.com', 'port' => 587, 'api' => false],
        'resend' => ['name' => 'Resend', 'host' => 'smtp.resend.com', 'port' => 465, 'login' => 'resend', 'api' => false],
        'custom' => ['name' => 'SMTP', 'host' => '', 'port' => 587, 'api' => false],
    ];

    public static function provider(): string
    {
        $key = (string) Setting::get('smtp_provider', 'brevo');

        return isset(self::PROVIDERS[$key]) ? $key : 'custom';
    }

    public static function smtp(): array
    {
        return [
            'host' => Setting::get('smtp_host', 'smtp-relay.brevo.com'),
            'port' => (int) Setting::get('smtp_port', '587'),
            'username' => Setting::get('smtp_username'),
            'password' => Setting::get('smtp_password'),
        ];
    }

    public static function configured(): bool
    {
        $smtp = self::smtp();

        return $smtp['host'] && $smtp['username'] && $smtp['password'];
    }

    public static function transport(): EsmtpTransport
    {
        if (! self::configured()) {
            throw new RuntimeException('SMTP is not configured. Save the Brevo SMTP settings in /admin first.');
        }

        $smtp = self::smtp();
        $transport = new EsmtpTransport($smtp['host'], $smtp['port'], $smtp['port'] === 465);
        $transport->setUsername($smtp['username']);
        $transport->setPassword($smtp['password']);

        return $transport;
    }

    /** Point Laravel's own mailer (password reset notices) at the same relay. */
    public static function applyToLaravel(): void
    {
        $smtp = self::smtp();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => $smtp['port'],
            'mail.mailers.smtp.username' => $smtp['username'],
            'mail.mailers.smtp.password' => $smtp['password'],
            'mail.from.address' => Setting::get('system_from', 'hello@'.config('inbound.domain')),
            'mail.from.name' => 'OpenMeru Mail',
        ]);
        Mail::purge('smtp');
    }

    /**
     * @param  array{to: string[], cc: string[], bcc: string[], subject: string, body: string}  $data
     * @param  UploadedFile[]  $uploads
     */
    public function send(User $user, array $data, array $uploads = [], ?Message $replyTo = null, ?Message $draft = null): Message
    {
        $domain = Str::after($user->email, '@');
        $messageId = Str::uuid()->toString().'@'.$domain;
        $text = $this->withSignature($user, $data['body']);
        // Formatted body from the editor (already cleaned); plain text otherwise.
        $rich = HtmlSanitizer::clean($data['html'] ?? '');
        $html = $rich !== '' ? $this->wrapHtml($rich.$this->signatureHtml($user)) : $this->toHtml($text);

        $email = (new Email)
            ->from(new Address($user->email, $user->name))
            ->subject($data['subject'])
            ->text($text)
            ->html($html);

        foreach ($data['to'] as $address) {
            $email->addTo($address);
        }
        foreach ($data['cc'] as $address) {
            $email->addCc($address);
        }
        foreach ($data['bcc'] as $address) {
            $email->addBcc($address);
        }

        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', $messageId);

        if ($replyTo && $replyTo->message_id) {
            $references = array_values(array_filter(array_merge(
                preg_split('/\s+/', trim((string) $replyTo->references)) ?: [],
                [$replyTo->message_id]
            )));
            $headers->addIdHeader('In-Reply-To', $replyTo->message_id);
            $headers->addIdHeader('References', array_map(fn ($id) => trim($id, '<>'), $references));
        }

        foreach ($uploads as $file) {
            $email->attachFromPath($file->getRealPath(), $file->getClientOriginalName(), $file->getMimeType());
        }

        (new Mailer(self::transport()))->send($email);

        $message = $draft ?? new Message;
        $message->user_id = $user->id;
        $message->forceFill([
            'folder' => 'sent',
            'message_id' => $messageId,
            'in_reply_to' => $replyTo?->message_id,
            'references' => $replyTo ? trim(($replyTo->references ? $replyTo->references.' ' : '').$replyTo->message_id) : null,
            'from_name' => $user->name,
            'from_email' => $user->email,
            'to' => $data['to'],
            'cc' => $data['cc'],
            'bcc' => $data['bcc'],
            'subject' => $data['subject'],
            'text_body' => $text,
            'html_body' => $rich !== '' ? $rich.$this->signatureHtml($user) : null,
            'snippet' => Message::makeSnippet($text, null),
            'is_read' => true,
            'has_attachments' => count($uploads) > 0 || ($draft && $draft->attachments()->exists()),
            'date' => now(),
        ])->save();

        foreach ($uploads as $file) {
            $path = $file->store('attachments/'.$user->id, 'local');
            $message->attachments()->create([
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
            ]);
        }

        return $message;
    }

    /**
     * Automatic forwarding: re-sends a received message from the mailbox's
     * own address (the original sender is kept as Reply-To), since the relay
     * only accepts senders on the mailbox domain.
     */
    public function forwardCopy(User $user, Message $message): void
    {
        $from = $message->from_name ? $message->from_name.' <'.$message->from_email.'>' : (string) $message->from_email;
        // Written in the mailbox owner's language.
        $locale = Locales::supported($user->locale) ? $user->locale : 'en';
        $header = __('forwarded', [], $locale)."\n"
            .__('from', [], $locale).": {$from}\n"
            .__('mail.date', [], $locale).': '.optional($message->date)->format('d/m/Y H:i')."\n"
            .__('subject', [], $locale).': '.$message->subject."\n"
            .__('to', [], $locale).': '.implode(', ', $message->to ?? [])."\n\n";

        $email = (new Email)
            ->from(new Address($user->email, $user->name))
            ->to($user->forward_to)
            ->subject((string) ($message->subject ?: __('noSubject', [], $locale)))
            ->text($header.($message->text_body ?? strip_tags((string) $message->html_body)));

        if ($message->html_body) {
            $email->html('<div style="font-family:Arial,sans-serif;font-size:13px;color:#555;border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:12px">'
                .nl2br(e($header), false).'</div>'.$message->html_body);
        }
        if ($message->reply_to || $message->from_email) {
            $email->replyTo($message->reply_to ?: $message->from_email);
        }
        $email->getHeaders()->addTextHeader('X-OpenMeru-Forwarded', $user->email);

        foreach ($message->attachments as $attachment) {
            $path = storage_path('app/private/'.$attachment->path);
            if (is_file($path)) {
                $email->attachFromPath($path, $attachment->filename, $attachment->mime ?: null);
            }
        }

        (new Mailer(self::transport()))->send($email);
    }

    private function withSignature(User $user, string $body): string
    {
        $signature = trim((string) $user->signature);

        return $signature === '' ? $body : rtrim($body)."\n\n-- \n".$signature;
    }

    private function signatureHtml(User $user): string
    {
        $signature = trim((string) $user->signature);

        return $signature === '' ? '' : '<p>-- <br>'.nl2br(e($signature), false).'</p>';
    }

    private function wrapHtml(string $inner): string
    {
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#1b2230">'.$inner.'</div>';
    }

    private function toHtml(string $text): string
    {
        $escaped = nl2br(e($text), false);

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#1b2230">'.$escaped.'</div>';
    }
}
