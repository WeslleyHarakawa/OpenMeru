<?php

namespace App\Http\Controllers;

use App\Mail\Outbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** The "install it for me" order form on the openmeru.com landing. */
class HireController extends Controller
{
    public const PRICE_USD = 100;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'panel' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $id = DB::table('install_requests')->insertGetId([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'domain' => isset($data['domain']) ? mb_strtolower(trim($data['domain'])) : null,
            'panel' => $data['panel'] ?? null,
            'notes' => $data['notes'] ?? null,
            'locale' => app()->getLocale(),
            'created_at' => now(),
        ]);

        // Tell the owner; the order is kept even when mail is not set up yet.
        $to = (string) config('inbound.otp_email');
        if ($to !== '' && Outbox::configured()) {
            try {
                Outbox::applyToLaravel();
                $body = "New install order #{$id}\n\nName: {$data['name']}\nEmail: {$data['email']}\nDomain: ".($data['domain'] ?? '-')."\nPanel: ".($data['panel'] ?? '-')."\nNotes: ".($data['notes'] ?? '-')."\n\nList them all: php artisan openmeru:requests";
                Mail::raw($body, fn ($m) => $m->to($to)->subject('OpenMeru: install order #'.$id.' from '.$data['email']));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'message' => __('landing.hireDone')]);
    }
}
