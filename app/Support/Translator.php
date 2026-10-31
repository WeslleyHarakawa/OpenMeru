<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-side translation through Cloudflare Workers AI (m2m100), used when
 * the visitor's browser has no built-in translator. Each piece of text is
 * cached for 30 days, so the same newsletter is only translated once.
 */
class Translator
{
    private const MODEL = '@cf/meta/m2m100-1.2b';

    /** Longest piece sent in one call; longer text is split by sentence. */
    private const CHUNK = 900;

    public static function configured(): bool
    {
        return Setting::get('cf_account_id') && Setting::get('cf_ai_token');
    }

    /**
     * @param  string[]  $segments
     * @return string[] translations, in the same order
     */
    public static function translate(array $segments, string $from, string $to): array
    {
        if (! self::configured()) {
            throw new RuntimeException('translate_unconfigured');
        }

        // Split long segments into chunks, remembering where each chunk goes.
        $chunks = [];
        $layout = [];
        foreach ($segments as $i => $segment) {
            foreach (self::split($segment) as $piece) {
                $layout[$i][] = count($chunks);
                $chunks[] = $piece;
            }
        }

        $results = [];
        $missing = [];
        foreach ($chunks as $n => $text) {
            $cached = Cache::get(self::key($text, $from, $to));
            if ($cached !== null) {
                $results[$n] = $cached;
            } elseif (trim($text) === '' || ! preg_match('/\p{L}/u', $text)) {
                $results[$n] = $text;
            } else {
                $missing[$n] = $text;
            }
        }

        foreach (array_chunk($missing, 8, true) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch, $from, $to) {
                foreach ($batch as $n => $text) {
                    $pool->as((string) $n)
                        ->withToken((string) Setting::get('cf_ai_token'))
                        ->timeout(25)
                        ->post(self::endpoint(), ['text' => $text, 'source_lang' => $from, 'target_lang' => $to]);
                }
            });
            foreach ($batch as $n => $text) {
                $response = $responses[(string) $n] ?? null;
                $translated = $response instanceof \Illuminate\Http\Client\Response && $response->successful()
                    ? $response->json('result.translated_text')
                    : null;
                if (! is_string($translated) || $translated === '') {
                    $status = $response instanceof \Illuminate\Http\Client\Response ? $response->status() : 0;
                    throw new RuntimeException('translate_failed:'.$status);
                }
                Cache::put(self::key($text, $from, $to), $translated, now()->addDays(30));
                $results[$n] = $translated;
            }
        }

        $out = [];
        foreach ($segments as $i => $segment) {
            $out[$i] = implode(' ', array_map(fn ($n) => $results[$n], $layout[$i] ?? []));
        }

        return $out;
    }

    private static function endpoint(): string
    {
        return 'https://api.cloudflare.com/client/v4/accounts/'.rawurlencode((string) Setting::get('cf_account_id')).'/ai/run/'.self::MODEL;
    }

    private static function key(string $text, string $from, string $to): string
    {
        return 'tr:'.$from.':'.$to.':'.sha1($text);
    }

    /** @return string[] */
    private static function split(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK) {
            return [$text];
        }
        $sentences = preg_split('/(?<=[.!?。！？])\s+/u', $text) ?: [$text];
        $pieces = [];
        $current = '';
        foreach ($sentences as $sentence) {
            while (mb_strlen($sentence) > self::CHUNK) {
                $pieces[] = mb_substr($sentence, 0, self::CHUNK);
                $sentence = mb_substr($sentence, self::CHUNK);
            }
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > self::CHUNK) {
                $pieces[] = $current;
                $current = '';
            }
            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
