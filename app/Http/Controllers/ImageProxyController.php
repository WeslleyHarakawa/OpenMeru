<?php

namespace App\Http\Controllers;

use App\Support\ImageProxy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImageProxyController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $src = (string) $request->query('u', '');
        abort_unless($src !== '' && strlen($src) <= 4096 && hash_equals(ImageProxy::sign($src), (string) $request->query('s', '')), 404);

        $image = ImageProxy::get($src);
        if ($image === null) {
            // Some senders block our server; let the reader's browser try the original then.
            return redirect()->away($src, 302)->withHeaders(['Cache-Control' => 'no-store']);
        }

        return response($image[0], 200, [
            'Content-Type' => $image[1],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
