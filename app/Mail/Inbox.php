<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message as MimeMessage;

/**
 * Files a raw RFC 822 message (delivered by the Cloudflare Email Worker)
 * into the inbox of the mailbox it was addressed to.
 */
class Inbox
{
    public function deliver(User $user, string $raw): Message
    {
        $mime = (new MailMimeParser)->parse($raw, false);
        $messageId = $this->id($mime->getHeaderValue(HeaderConsts::MESSAGE_ID));

        if ($messageId && $existing = $user->messages()->where('message_id', $messageId)->where('folder', '!=', 'sent')->first()) {
            return $existing;
        }

        $from = $mime->getHeader(HeaderConsts::FROM);
        $fromEmail = $from instanceof AddressHeader ? $from->getEmail() : null;
        $fromName = $from instanceof AddressHeader ? $from->getPersonName() : null;
        $dateHeader = $mime->getHeader(HeaderConsts::DATE);
        $date = $dateHeader instanceof DateHeader ? $dateHeader->getDateTime() : null;

        $text = $mime->getTextContent();
        $html = $mime->getHtmlContent();

        $rawPath = 'raw/'.$user->id.'/'.Str::uuid().'.eml';
        Storage::disk('local')->put($rawPath, $raw);

        $message = new Message;
        $message->user_id = $user->id;
        $message->forceFill([
            'folder' => 'inbox',
            'message_id' => $messageId,
            'in_reply_to' => $this->id($mime->getHeaderValue(HeaderConsts::IN_REPLY_TO)),
            'references' => $this->references($mime->getHeader(HeaderConsts::REFERENCES)?->getRawValue()),
            'from_name' => $fromName ?: null,
            'from_email' => $fromEmail ? mb_strtolower($fromEmail) : null,
            'to' => $this->addresses($mime, HeaderConsts::TO),
            'cc' => $this->addresses($mime, HeaderConsts::CC),
            'bcc' => [],
            'reply_to' => $this->firstAddress($mime, HeaderConsts::REPLY_TO),
            'subject' => mb_substr((string) $mime->getHeaderValue(HeaderConsts::SUBJECT), 0, 998),
            'text_body' => $text,
            'html_body' => $html,
            'snippet' => Message::makeSnippet($text, $html),
            'is_read' => false,
            'raw_path' => $rawPath,
            'date' => $date && $date->getTimestamp() > 0 ? $date : now(),
        ])->save();

        $inline = [];
        foreach ($mime->getAllAttachmentParts() as $part) {
            $filename = $part->getFilename() ?: 'attachment-'.Str::random(6);
            $content = $part->getContent() ?? '';
            $path = 'attachments/'.$user->id.'/'.Str::uuid();
            Storage::disk('local')->put($path, $content);

            $attachment = $message->attachments()->create([
                'filename' => mb_substr($filename, 0, 255),
                'mime' => $part->getContentType(),
                'size' => strlen($content),
                'content_id' => $part->getContentId(),
                'path' => $path,
            ]);

            if ($cid = $part->getContentId()) {
                $inline['cid:'.trim($cid, '<>')] = route('attachments.show', $attachment);
            }
        }

        if ($message->attachments()->exists()) {
            $message->has_attachments = true;
        }
        if ($html && $inline) {
            $message->html_body = strtr($html, $inline);
        }
        $message->save();

        return $message;
    }

    private function id(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr(trim($value, '<> '), 0, 255);
    }

    private function references(?string $value): ?string
    {
        preg_match_all('/<([^>]+)>/', (string) $value, $matches);

        return $matches[1] ? implode(' ', $matches[1]) : null;
    }

    private function addresses(MimeMessage $mime, string $name): array
    {
        $header = $mime->getHeader($name);
        if (! $header instanceof AddressHeader) {
            return [];
        }

        return array_values(array_map(
            fn ($address) => $address->getName() ? $address->getName().' <'.$address->getEmail().'>' : $address->getEmail(),
            $header->getAddresses()
        ));
    }

    private function firstAddress(MimeMessage $mime, string $name): ?string
    {
        $header = $mime->getHeader($name);

        return $header instanceof AddressHeader ? $header->getEmail() : null;
    }
}
