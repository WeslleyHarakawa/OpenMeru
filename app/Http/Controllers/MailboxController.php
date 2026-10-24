<?php

namespace App\Http\Controllers;

use App\Mail\Outbox;
use App\Models\Attachment;
use App\Models\Message;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Every query here goes through $request->user()->messages(), so a mailbox
 * only ever sees its own mail.
 */
class MailboxController extends Controller
{
    /** /mail: the sign-in page for visitors, the mailbox once signed in. */
    public function entry(Request $request): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $user = null;
        }

        $view = $user ? view('mail.inbox', ['user' => $user]) : view('auth.login');

        return response($view)->header('Cache-Control', 'no-store, private');
    }

    public function page(Request $request): View
    {
        return view('mail.inbox', ['user' => $request->user()]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'signature' => $user->signature,
            'recovery_email' => $user->recovery_email,
            'forward_to' => $user->forward_to,
            'forward_keep' => $user->forward_keep,
            'counts' => $this->counts($request),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $folder = $request->query('folder', 'inbox');
        $query = $request->user()->messages()->orderByDesc('date')->orderByDesc('id');

        if ($folder === 'starred') {
            $query->where('is_starred', true)->whereNotIn('folder', ['trash', 'spam']);
        } elseif (in_array($folder, Message::FOLDERS, true)) {
            $query->where('folder', $folder);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(function (Builder $w) use ($like) {
                $w->where('subject', 'like', $like)
                    ->orWhere('from_email', 'like', $like)
                    ->orWhere('from_name', 'like', $like)
                    ->orWhereRaw('CAST(`to` AS CHAR) LIKE ?', [$like])
                    ->orWhere('snippet', 'like', $like);
            });
        }

        $page = $query->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map->summary(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            // Lets an open tab notice that this browser signed into another mailbox.
            'account' => $request->user()->email,
            'counts' => $this->counts($request),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $message = $this->find($request, $id);
        if (! $message->is_read) {
            $message->forceFill(['is_read' => true])->save();
        }

        return response()->json($message->load('attachments')->detail());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $message = $this->find($request, $id);
        $data = $request->validate([
            'is_read' => ['sometimes', 'boolean'],
            'is_starred' => ['sometimes', 'boolean'],
            'folder' => ['sometimes', 'in:inbox,spam,trash,archive'],
        ]);
        if (isset($data['folder']) && in_array($message->folder, ['sent', 'drafts'], true) && $data['folder'] === 'inbox') {
            unset($data['folder']);
        }
        $message->forceFill($data)->save();

        return response()->json(['ok' => true, 'counts' => $this->counts($request)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $message = $this->find($request, $id);

        if ($message->folder !== 'trash' && $message->folder !== 'drafts') {
            $message->forceFill(['folder' => 'trash'])->save();
        } else {
            $this->purge($message);
        }

        return response()->json(['ok' => true, 'counts' => $this->counts($request)]);
    }

    public function send(Request $request, Outbox $outbox): JsonResponse
    {
        $data = $this->validateCompose($request, true);
        $user = $request->user();
        $replyTo = $request->filled('reply_to_id') ? $this->find($request, (int) $request->input('reply_to_id')) : null;
        $draft = $request->filled('draft_id') ? $this->find($request, (int) $request->input('draft_id')) : null;
        if ($draft && $draft->folder !== 'drafts') {
            $draft = null;
        }

        try {
            $message = $outbox->send($user, $data, $request->file('attachments', []), $replyTo, $draft);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'send_failed', 'detail' => $e->getMessage()], 502);
        }

        return response()->json(['id' => $message->id, 'counts' => $this->counts($request)]);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $data = $this->validateCompose($request, false);
        $user = $request->user();
        $draft = $request->filled('draft_id') ? $this->find($request, (int) $request->input('draft_id')) : new Message;
        if ($draft->exists && $draft->folder !== 'drafts') {
            $draft = new Message;
        }

        $draft->user_id = $user->id;
        $draft->forceFill([
            'folder' => 'drafts',
            'from_name' => $user->name,
            'from_email' => $user->email,
            'to' => $data['to'],
            'cc' => $data['cc'],
            'bcc' => $data['bcc'],
            'subject' => $data['subject'],
            'text_body' => $data['body'],
            'html_body' => HtmlSanitizer::clean($data['html']) ?: null,
            'snippet' => Message::makeSnippet($data['body'], null),
            'in_reply_to' => $request->input('reply_to_id') ? $this->find($request, (int) $request->input('reply_to_id'))->message_id : $draft->in_reply_to,
            'is_read' => true,
            'date' => now(),
        ])->save();

        return response()->json(['id' => $draft->id, 'counts' => $this->counts($request)]);
    }

    public function attachment(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->message && $attachment->message->user_id === $request->user()->id, 404);

        $inline = $request->boolean('inline') || str_starts_with((string) $attachment->mime, 'image/');

        return Storage::disk('local')->response($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    private function find(Request $request, int $id): Message
    {
        return $request->user()->messages()->findOrFail($id);
    }

    private function purge(Message $message): void
    {
        foreach ($message->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
        if ($message->raw_path) {
            Storage::disk('local')->delete($message->raw_path);
        }
        $message->delete();
    }

    private function counts(Request $request): array
    {
        $rows = $request->user()->messages()
            ->selectRaw('folder, count(*) as total, sum(case when is_read = 0 then 1 else 0 end) as unread')
            ->groupBy('folder')
            ->get()
            ->keyBy('folder');

        $counts = [];
        foreach (Message::FOLDERS as $folder) {
            $counts[$folder] = [
                'total' => (int) ($rows[$folder]->total ?? 0),
                'unread' => (int) ($rows[$folder]->unread ?? 0),
            ];
        }
        $starred = $request->user()->messages()->where('is_starred', true)->whereNotIn('folder', ['trash', 'spam'])->count();
        $counts['starred'] = ['total' => $starred, 'unread' => 0];

        return $counts;
    }

    /** @return array{to: string[], cc: string[], bcc: string[], subject: string, body: string} */
    private function validateCompose(Request $request, bool $sending): array
    {
        $request->validate([
            'subject' => ['nullable', 'string', 'max:998'],
            'body' => ['nullable', 'string', 'max:500000'],
            'body_html' => ['nullable', 'string', 'max:1000000'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => ['file', 'max:25600'],
        ]);

        $fields = [];
        foreach (['to', 'cc', 'bcc'] as $field) {
            $fields[$field] = $this->parseAddresses((string) $request->input($field), $field);
        }

        if ($sending && ! $fields['to'] && ! $fields['cc'] && ! $fields['bcc']) {
            throw ValidationException::withMessages(['to' => 'needTo']);
        }

        return $fields + [
            'subject' => trim((string) $request->input('subject')),
            'body' => (string) $request->input('body'),
            'html' => (string) $request->input('body_html'),
        ];
    }

    /** @return string[] */
    private function parseAddresses(string $value, string $field): array
    {
        $list = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $value) ?: [])));
        foreach ($list as $address) {
            if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([$field => 'invalid:'.$address]);
            }
        }

        return array_values(array_unique(array_map('mb_strtolower', $list)));
    }
}
