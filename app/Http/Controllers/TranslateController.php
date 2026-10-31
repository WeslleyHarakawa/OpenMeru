<?php

namespace App\Http\Controllers;

use App\Support\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** Fallback translation for browsers without a built-in translator. */
class TranslateController extends Controller
{
    public function translate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'regex:/^[a-z]{2,3}$/'],
            'target' => ['required', 'string', 'regex:/^[a-z]{2,3}$/'],
            'segments' => ['required', 'array', 'max:300'],
            'segments.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $segments = array_map(fn ($s) => (string) $s, $data['segments']);
        if (array_sum(array_map('mb_strlen', $segments)) > 30000) {
            return response()->json(['message' => 'too_long'], 422);
        }
        if (! Translator::configured()) {
            return response()->json(['message' => 'translate_unconfigured'], 503);
        }

        try {
            return response()->json(['translations' => Translator::translate($segments, $data['source'], $data['target'])]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'translate_failed'], 502);
        }
    }
}
