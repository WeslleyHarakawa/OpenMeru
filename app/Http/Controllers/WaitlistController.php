<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The "notify me when cloud hosting launches" box on the openmeru.com landing. */
class WaitlistController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));

        DB::table('cloud_waitlist')->upsert(
            [['email' => $email, 'locale' => app()->getLocale(), 'created_at' => now()]],
            ['email'],
            ['locale']
        );

        return response()->json(['ok' => true, 'message' => __('landing.waitDone')]);
    }
}
