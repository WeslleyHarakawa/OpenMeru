<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use App\Support\Version;
use Illuminate\Http\Response;

/**
 * What crawlers get. Only openmeru.com (the source install) is a public site:
 * every other install is somebody's private webmail and asks not to be indexed.
 */
class SeoController extends Controller
{
    public const REPO = 'https://github.com/WeslleyHarakawa/OpenMeru';

    public function robots(): Response
    {
        $lines = config('updates.source')
            ? ['User-agent: *', 'Allow: /', 'Disallow: /mail', 'Disallow: /admin', 'Disallow: /demo', 'Disallow: /hooks/', 'Disallow: /api/', '', 'Sitemap: '.url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return $this->text(implode("\n", $lines)."\n");
    }

    public function sitemap(): Response
    {
        $modified = Version::releasedAt() ? substr(Version::releasedAt(), 0, 10) : date('Y-m-d');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach (array_keys(Locales::ALL) as $code) {
            $xml .= "  <url>\n    <loc>".e(self::pageUrl($code))."</loc>\n    <lastmod>{$modified}</lastmod>\n";
            foreach (Locales::ALL as $alt => [, $tag]) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.$tag.'" href="'.e(self::pageUrl($alt)).'"/>'."\n";
            }
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="'.e(url('/')).'"/>'."\n  </url>\n";
        }
        foreach (['/privacy', '/terms'] as $path) {
            $xml .= "  <url>\n    <loc>".e(url($path))."</loc>\n    <lastmod>{$modified}</lastmod>\n  </url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** A plain-text summary for AI assistants (llmstxt.org). */
    public function llms(): Response
    {
        $base = rtrim(url('/'), '/');
        $repo = self::REPO;
        $languages = implode(', ', array_map(fn ($l) => $l[0], Locales::ALL));
        $text = <<<TXT
# OpenMeru Mail

> Open-source (MIT) self-hosted webmail for your own domain, version {$this->version()}. Sends through Brevo or any SMTP relay, receives through Cloudflare Email Routing plus a small Email Worker, and keeps a copy of every incoming message in a Gmail account. No mail server to run: just PHP 8.5, MySQL and two free services.

Author: Weslley Harakawa (https://www.linkedin.com/in/weslleyharakawa)
Website: {$base}/
Source code: {$repo}
License: MIT

## What it does

- Admin panel at /admin: create, search, open and delete mailboxes on your domain; admins sign in with a password plus an emailed code.
- Private mailboxes with inbox, sent, drafts, starred, archive, spam and trash; rich editor, Cc/Bcc, attachments up to 25 MB, signatures, replies, auto-forwarding.
- Two-factor authentication (TOTP: Google Authenticator, Authy, ...) with recovery codes; the admin can require it for every account.
- Optional self-service sign-up, push notifications, 10 interface languages: {$languages}.
- Branding: name, logo, icon, favicon, sign-in background and colours.
- Sending presets: Brevo (default), SMTP2GO, Mailgun, Postmark, SendGrid, Amazon SES, Resend, or any SMTP server.
- Self-updating: each install checks {$base}/version daily and updates itself (database backup, new code, dependencies, migrations); works with or without git.

## Install

One line on the server, as the site user:

    curl -fsSL {$base}/install | bash

Requirements: Linux VPS with PHP 8.5, MySQL 8 or MariaDB, Composer, nginx or Apache; DNS on Cloudflare; a Brevo (or other SMTP) account; a Gmail account for the catch-all copy and admin codes.

## Pages

- [Landing and install guide]({$base}/): overview, live demo, how it works, step-by-step install, FAQ. Add ?lang=pt, es, fr, de, it, nl, ru, zh or pt_BR for a translation.
- [Live demo]({$base}/demo): read-only demo mailbox; [demo admin]({$base}/demo/admin).
- [Version feed]({$base}/version): JSON with the current version, repository and package URLs.
- [Installer script]({$base}/install): the one-line installer's source.
- [Privacy]({$base}/privacy), [Terms]({$base}/terms).
- [README]({$repo}#readme), [Cloudflare Email Worker]({$repo}/blob/main/cloudflare-email-worker.js).
TXT;

        return $this->text($text."\n");
    }

    private function version(): string
    {
        return Version::current();
    }

    public static function pageUrl(string $code): string
    {
        $root = rtrim(url('/'), '/').'/';

        return $code === 'en' ? $root : $root.'?lang='.$code;
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
