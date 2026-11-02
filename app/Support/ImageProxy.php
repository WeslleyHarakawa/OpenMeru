<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Loads the remote images of received mail through this server, like Gmail
 * does. Images still show when the reader's network blocks the sender's site,
 * and the sender never learns the reader's IP address.
 */
class ImageProxy
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const KEEP_DAYS = 7;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    /** Points every remote image in the mail at the proxy. */
    public static function rewrite(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // <img src>, <td background>, <input type=image src> and friends.
        $html = preg_replace_callback(
            '/(<[a-z][a-z0-9]*\b[^>]*?\s(?:src|background|poster)\s*=\s*)(["\']?)(https?:\/\/[^"\'\s>]+)\2/i',
            fn ($m) => $m[1].'"'.self::attr($m[3]).'"',
            $html
        );

        // srcset would let the browser skip the proxy, so keep only the plain src.
        $html = preg_replace('/\ssrcset\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        // CSS backgrounds, in style attributes and <style> blocks.
        return preg_replace_callback(
            '/url\(\s*(["\']|&quot;|&#39;)?(https?:\/\/[^"\')\s]+?)\1?\s*\)/i',
            fn ($m) => 'url('.self::attr($m[2]).')',
            $html
        );
    }

    public static function url(string $src): string
    {
        return route('img.proxy', ['u' => $src, 's' => self::sign($src)]);
    }

    public static function sign(string $src): string
    {
        return substr(hash_hmac('sha256', $src, (string) config('app.key')), 0, 32);
    }

    /**
     * @return array{0: string, 1: string}|null [bytes, mime type]
     */
    public static function get(string $src): ?array
    {
        $id = sha1($src);
        $disk = Storage::disk('local');
        $path = 'imgcache/'.substr($id, 0, 2).'/'.$id;

        if ($disk->exists($path) && $disk->lastModified($path) > time() - self::KEEP_DAYS * 86400) {
            $data = $disk->get($path);
            $mime = self::mime($data);

            return $mime ? [$data, $mime] : null;
        }
        if (Cache::has('imgfail:'.$id)) {
            return null;
        }

        $data = self::fetch($src);
        $mime = $data !== null ? self::mime($data) : null;
        if ($mime === null) {
            Cache::put('imgfail:'.$id, 1, now()->addHour());

            return null;
        }
        $disk->put($path, $data);

        return [$data, $mime];
    }

    private static function attr(string $src): string
    {
        $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        // Our own links (inline attachments) already come from this server.
        $out = $host !== '' && $host !== $own && $host !== 'www.'.$own ? self::url($src) : $src;

        return htmlspecialchars($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function mime(string $data): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: '';
        if (in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml'], true) || str_contains(substr($data, 0, 512), '<svg')) {
            return 'image/svg+xml';
        }

        return str_starts_with($mime, 'image/') ? $mime : null;
    }

    /** Downloads the image, following up to 4 redirects, never into a private network. */
    private static function fetch(string $src): ?string
    {
        $url = $src;
        for ($hop = 0; $hop < 5; $hop++) {
            $parts = parse_url($url);
            $scheme = strtolower($parts['scheme'] ?? '');
            $host = $parts['host'] ?? '';
            if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
                return null;
            }
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            $ip = self::publicIp($host);
            if ($ip === null) {
                return null;
            }

            $body = '';
            $tooBig = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/png,image/*;q=0.8,*/*;q=0.5'],
                CURLOPT_ENCODING => '',
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$tooBig) {
                    $body .= $chunk;
                    if (strlen($body) > self::MAX_BYTES) {
                        $tooBig = true;

                        return 0;
                    }

                    return strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $next = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($tooBig) {
                return null;
            }
            if ($status >= 300 && $status < 400 && $next !== '') {
                $url = $next;

                continue;
            }

            return $status === 200 && $body !== '' ? $body : null;
        }

        return null;
    }

    private static function publicIp(string $host): ?string
    {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
            $ips = array_values(array_filter($ips));
        }
        if (! $ips) {
            return null;
        }
        foreach ($ips as $ip) {
            $cgnat = str_contains($ip, '.') && (ip2long($ip) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000);
            if ($cgnat || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $ips[0];
    }
}
