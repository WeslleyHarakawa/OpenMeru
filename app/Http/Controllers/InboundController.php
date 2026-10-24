<?php

namespace App\Http\Controllers;

use App\Mail\Inbox;
use App\Mail\Outbox;
use App\Models\User;
use App\Support\Push;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Receives mail from the Cloudflare Email Worker: the raw message in the
 * body, the envelope recipient in ?to=, and the shared secret in a header.
 */
class InboundController extends Controller
{
    public function store(Request $request, Inbox $inbox, Outbox $outbox): JsonResponse
    {
        $secret = (string) config('inbound.secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-OpenMeru-Secret'))) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        $to = mb_strtolower(trim((string) $request->query('to')));
        $raw = $request->getContent();
        if ($to === '' || $raw === '') {
            return response()->json(['message' => 'missing to or body'], 422);
        }

        $user = User::where('email', $to)->where('is_active', true)->where('is_demo', false)->first();
        if (! $user) {
            // No mailbox for this address: the Worker's Gmail copy is the only one.
            return response()->json(['stored' => false, 'reason' => 'no_mailbox'], 202);
        }

        $message = $inbox->deliver($user, $raw);
        if (! $message->wasRecentlyCreated) {
            return response()->json(['stored' => true, 'id' => $message->id, 'duplicate' => true]);
        }

        Push::newMail($user, $message);

        // A message that was already forwarded by OpenMeru Mail is never forwarded again (no loops).
        if ($user->forward_to && ! preg_match('/^X-OpenMeru-Forwarded:/mi', substr($raw, 0, 65536))) {
            try {
                $outbox->forwardCopy($user, $message->load('attachments'));
                if (! $user->forward_keep) {
                    $message->forceFill(['folder' => 'archive', 'is_read' => true])->save();
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['stored' => true, 'id' => $message->id]);
    }
}
