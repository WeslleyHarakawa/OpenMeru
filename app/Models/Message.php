<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public const FOLDERS = ['inbox', 'sent', 'drafts', 'spam', 'trash', 'archive'];

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'has_attachments' => 'boolean',
            'date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public static function makeSnippet(?string $text, ?string $html): string
    {
        $source = $text !== null && trim($text) !== '' ? $text : strip_tags((string) $html);
        $source = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $source)), 0, 200);
    }

    public function summary(): array
    {
        return [
            'id' => $this->id,
            'folder' => $this->folder,
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'to' => $this->to ?? [],
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'is_read' => $this->is_read,
            'is_starred' => $this->is_starred,
            'has_attachments' => $this->has_attachments,
            'date' => optional($this->date)->toIso8601String(),
        ];
    }

    public function detail(): array
    {
        return $this->summary() + [
            'cc' => $this->cc ?? [],
            'bcc' => $this->bcc ?? [],
            'reply_to' => $this->reply_to,
            'message_id' => $this->message_id,
            'text_body' => $this->text_body,
            'html_body' => $this->html_body,
            'attachments' => $this->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'filename' => $a->filename,
                'mime' => $a->mime,
                'size' => $a->size,
                'url' => route('attachments.show', $a),
            ])->values(),
        ];
    }
}
