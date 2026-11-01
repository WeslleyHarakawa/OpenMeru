<?php

namespace App\Http\Controllers;

use App\Support\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * openmeru.com/mcp: a Model Context Protocol server (Streamable HTTP, stateless) so an
 * AI coding agent — Claude Code, Codex, Cursor, anything that speaks MCP — can install,
 * configure and troubleshoot OpenMeru on the user's server. The agent runs the commands;
 * this server knows the product: the exact installer flags, the Cloudflare and Brevo
 * steps, the DNS records and the fixes for the usual failures.
 */
class McpController extends Controller
{
    public const PROTOCOL = '2025-03-26';

    public const NAME = 'openmeru';

    /** Browsers and curl land here: tell them what this is instead of a bare 405. */
    public function describe(): JsonResponse
    {
        return response()->json([
            'name' => self::NAME,
            'description' => 'OpenMeru MCP server. Connect an AI coding agent to install and run OpenMeru Mail.',
            'transport' => 'streamable-http',
            'endpoint' => url('/mcp'),
            'claude_code' => 'claude mcp add --transport http openmeru '.url('/mcp'),
            'codex' => 'codex mcp add openmeru --url '.url('/mcp'),
            'cursor' => ['mcpServers' => ['openmeru' => ['url' => url('/mcp')]]],
            'tools' => array_map(fn ($t) => $t['name'], $this->tools()),
            'docs' => url('/docs'),
        ], 405, ['Allow' => 'POST']);
    }

    public function handle(Request $request): Response|JsonResponse
    {
        $message = $request->json()->all();
        if (! is_array($message) || ! isset($message['method'])) {
            return $this->error(null, -32600, 'Invalid Request: expected a JSON-RPC 2.0 message');
        }
        $id = $message['id'] ?? null;
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        // Notifications (no id) are acknowledged with an empty 202.
        if ($id === null && str_starts_with((string) $message['method'], 'notifications/')) {
            return response('', 202);
        }

        return match ($message['method']) {
            'initialize' => $this->result($id, [
                'protocolVersion' => self::PROTOCOL,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => self::NAME, 'version' => Version::current()],
                'instructions' => $this->instructions(),
            ]),
            'ping' => $this->result($id, (object) []),
            'tools/list' => $this->result($id, ['tools' => $this->tools()]),
            'tools/call' => $this->call($id, (string) ($params['name'] ?? ''), is_array($params['arguments'] ?? null) ? $params['arguments'] : []),
            'resources/list' => $this->result($id, ['resources' => []]),
            'prompts/list' => $this->result($id, ['prompts' => []]),
            default => $this->error($id, -32601, 'Method not found: '.$message['method']),
        };
    }

    private function instructions(): string
    {
        return 'OpenMeru Mail is an open-source webmail (PHP 8.5 + MySQL) that sends through Brevo or any SMTP relay and receives through Cloudflare Email Routing + an Email Worker, keeping a Gmail copy. '
            .'To install it for a user: 1) call openmeru_install_plan with their domain and details, 2) run the commands it returns on their server (as the site user, never root), 3) use openmeru_cloudflare_worker and openmeru_dns_records for the Cloudflare and Brevo side, 4) openmeru_troubleshoot when something fails. '
            .'Never print or store passwords and API keys in files other than the .env the installer writes; the installer asks for secrets without echo.';
    }

    /** @return array<int, array{name: string, description: string, inputSchema: array}> */
    private function tools(): array
    {
        $none = ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false];

        return [
            ['name' => 'openmeru_overview', 'description' => 'What OpenMeru Mail is, what it needs (server, domain on Cloudflare, Brevo, Gmail) and how the pieces fit. Read this first.', 'inputSchema' => $none],
            ['name' => 'openmeru_version', 'description' => 'The latest published version, the repository and the package URLs.', 'inputSchema' => $none],
            ['name' => 'openmeru_install_plan', 'description' => 'The exact, ordered shell commands and panel steps to install OpenMeru on a Linux server for a given domain. Run them on the user\'s server as the site user.', 'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'domain' => ['type' => 'string', 'description' => 'Mail domain, mailboxes are user@domain (e.g. example.com)'],
                    'url' => ['type' => 'string', 'description' => 'Public URL of the install, e.g. https://mail.example.com (default https://<domain>)'],
                    'otp_email' => ['type' => 'string', 'description' => 'Outside inbox (a Gmail) that receives the admin sign-in codes and the catch-all copy'],
                    'admin_email' => ['type' => 'string', 'description' => 'First admin mailbox, e.g. admin@example.com'],
                    'admin_name' => ['type' => 'string', 'description' => 'Display name of the first admin'],
                    'db_name' => ['type' => 'string', 'description' => 'MySQL database name (created beforehand)'],
                    'db_user' => ['type' => 'string', 'description' => 'MySQL user'],
                    'panel' => ['type' => 'string', 'enum' => ['cloudpanel', 'plain'], 'description' => 'cloudpanel for a CloudPanel server (default), plain for nginx/Apache set up by hand'],
                    'install_dir' => ['type' => 'string', 'description' => 'Where to put the app (default ~/openmeru-app)'],
                ],
                'required' => ['domain', 'otp_email', 'admin_email'],
                'additionalProperties' => false,
            ]],
            ['name' => 'openmeru_cloudflare_worker', 'description' => 'The Email Worker source code and the Cloudflare steps (Email Routing, destination address, Worker variables, routing rule) that deliver incoming mail to the install.', 'inputSchema' => [
                'type' => 'object',
                'properties' => ['domain' => ['type' => 'string'], 'url' => ['type' => 'string', 'description' => 'Public URL of the install'], 'gmail' => ['type' => 'string', 'description' => 'The Gmail that keeps a copy of every message']],
                'required' => ['domain'],
                'additionalProperties' => false,
            ]],
            ['name' => 'openmeru_dns_records', 'description' => 'The DNS records a domain needs (MX from Email Routing, one merged SPF for Cloudflare + Brevo, DKIM and DMARC from Brevo) and the Brevo sender setup.', 'inputSchema' => [
                'type' => 'object',
                'properties' => ['domain' => ['type' => 'string'], 'provider' => ['type' => 'string', 'enum' => ['brevo', 'smtp2go', 'mailgun', 'postmark', 'sendgrid', 'ses', 'resend', 'custom'], 'description' => 'Sending relay (default brevo)']],
                'required' => ['domain'],
                'additionalProperties' => false,
            ]],
            ['name' => 'openmeru_commands', 'description' => 'The artisan commands an install offers after setup: admin, smtp, domain, update, waitlist, and what each one asks for.', 'inputSchema' => $none],
            ['name' => 'openmeru_troubleshoot', 'description' => 'Known failures and their fixes: mail not arriving, 403 from the Worker, admin code not received, update failed, git not found, uploads refused, 2FA locked out.', 'inputSchema' => [
                'type' => 'object',
                'properties' => ['symptom' => ['type' => 'string', 'description' => 'What the user sees (error text or a short description)']],
                'additionalProperties' => false,
            ]],
        ];
    }

    private function call(mixed $id, string $name, array $args): JsonResponse
    {
        $text = match ($name) {
            'openmeru_overview' => $this->overview(),
            'openmeru_version' => $this->version(),
            'openmeru_install_plan' => $this->installPlan($args),
            'openmeru_cloudflare_worker' => $this->worker($args),
            'openmeru_dns_records' => $this->dns($args),
            'openmeru_commands' => $this->commands(),
            'openmeru_troubleshoot' => $this->troubleshoot((string) ($args['symptom'] ?? '')),
            default => null,
        };
        if ($text === null) {
            return $this->error($id, -32602, 'Unknown tool: '.$name);
        }

        return $this->result($id, ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false]);
    }

    private function result(mixed $id, mixed $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private static function host(string $value, string $default): string
    {
        $value = mb_strtolower(trim($value));

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $value) ? $value : $default;
    }

    private static function arg(string $value, string $fallback = ''): string
    {
        $value = trim($value);

        return $value === '' ? $fallback : escapeshellarg($value);
    }

    private function overview(): string
    {
        $base = rtrim(url('/'), '/');

        return <<<TXT
# OpenMeru Mail (version {$this->v()})

Open-source (MIT) self-hosted webmail for one domain. No mail server to run:
- Sending: Brevo (default, free tier 300/day) or any SMTP relay (SMTP2GO, Mailgun, Postmark, SendGrid, Amazon SES, Resend, custom).
- Receiving: Cloudflare Email Routing (free) catches *@domain and hands each message to a small Email Worker, which forwards a copy to a Gmail and POSTs the message to the install (/hooks/inbound, shared secret INBOUND_SECRET).
- Admin panel at /admin (password + emailed 6-digit code), mailboxes at the site root, 2FA (TOTP), push notifications, 10 languages, branding (name, logo, icon, favicon, colours, sign-in background), self-updating from {$base}/version.

Requirements on the server:
- Linux VPS, PHP 8.5+ with pdo_mysql mbstring openssl dom fileinfo curl ctype tokenizer xml, Composer, nginx or Apache. CloudPanel is the easy path (site user, document root, Let's Encrypt). git is optional.
- MySQL 8 / MariaDB database (or SQLite for tests).
- The domain's DNS on Cloudflare (free plan is enough).
- A Brevo account (or another relay) with the domain verified.
- A Gmail account: receives the catch-all copy and the admin sign-in codes.

Layout: the app lives in a folder (default ~/openmeru-app); the web root must point to <app>/public_root. Updates: /admin shows "Update now" and installs them itself (automatic by default); or run the install line again.

Flow for an agent: openmeru_install_plan → run the commands → openmeru_dns_records and openmeru_cloudflare_worker for the DNS/Cloudflare side → test at /admin and the inbox → openmeru_troubleshoot if needed.
Docs: {$base}/docs · Repo: https://github.com/WeslleyHarakawa/OpenMeru
TXT;
    }

    private function version(): string
    {
        $base = rtrim(url('/'), '/');

        return "Latest version: {$this->v()}\nRepository: https://github.com/WeslleyHarakawa/OpenMeru\nPackage (tar.gz): https://github.com/WeslleyHarakawa/OpenMeru/archive/refs/heads/main.tar.gz\nInstaller: {$base}/install\nVersion feed (JSON): {$base}/version";
    }

    private function installPlan(array $a): string
    {
        $domain = self::host((string) ($a['domain'] ?? ''), 'example.com');
        $url = trim((string) ($a['url'] ?? '')) ?: 'https://'.$domain;
        $url = preg_match('#^https?://[a-z0-9.-]+$#i', $url) ? rtrim($url, '/') : 'https://'.$domain;
        $otp = self::arg((string) ($a['otp_email'] ?? ''), "'yourproject@gmail.com'");
        $adminEmail = self::arg((string) ($a['admin_email'] ?? ''), "'admin@{$domain}'");
        $adminName = self::arg((string) ($a['admin_name'] ?? ''), "'Admin'");
        $dbName = self::arg((string) ($a['db_name'] ?? ''), "'openmeru'");
        $dbUser = self::arg((string) ($a['db_user'] ?? ''), "'openmeru'");
        $dir = trim((string) ($a['install_dir'] ?? '')) ?: '~/openmeru-app';
        $cloud = ($a['panel'] ?? 'cloudpanel') !== 'plain';
        $base = rtrim(url('/'), '/');
        $host = parse_url($url, PHP_URL_HOST) ?: $domain;

        $panelStep = $cloud
            ? "1. In CloudPanel: Sites → Add Site → PHP site for {$host} (PHP 8.5), note the site user. Databases → Add Database: name {$dbName}, user {$dbUser}, a strong password (keep it for step 3). SSH in as the site user (not root)."
            : "1. Create a Linux user for the site (never run the installer as root), a MySQL database {$dbName} with user {$dbUser} and a strong password, and an nginx/Apache vhost for {$host} with PHP 8.5-FPM.";
        $docroot = $cloud
            ? "4. CloudPanel → the site → Settings → Root Directory: point it at the app's public_root (e.g. {$host}/openmeru-app/public_root when the app lives inside the site folder; otherwise the absolute path {$dir}/public_root). Enable Let's Encrypt (SSL/TLS → Actions → New Let's Encrypt Certificate)."
            : "4. Set the vhost document root to {$dir}/public_root (front controller index.php; the folder ships .htaccess and .user.ini). Enable HTTPS (certbot).";

        return <<<TXT
# Install plan for {$domain} → {$url}

Run everything on the user's server, over SSH, as the site user. Secrets (database password, admin password, SMTP key) are typed into prompts without echo; never paste them into files or chat.

{$panelStep}

2. One-line installer (checks PHP 8.5, extensions, Composer; downloads the app into {$dir}; works with or without git):
   curl -fsSL {$base}/install | bash -s -- --dir {$dir} --domain={$domain} --url={$url} --otp-email={$otp} --db-name={$dbName} --db-user={$dbUser} --admin-email={$adminEmail} --admin-name={$adminName}
   It then asks interactively for: the database password, the admin password (min. 8 chars) and, optionally, the SMTP relay. Answer the prompts. (Add --no-interaction only when every answer is already in the .env.)
   It generates APP_KEY and INBOUND_SECRET, creates the tables and prints the two values you need in Cloudflare: INBOUND_URL ({$url}/hooks/inbound) and INBOUND_SECRET.

3. If the installer was run earlier and only settings changed: cd {$dir} && php artisan openmeru:install  (keeps what is set, asks only what is missing).

{$docroot}

5. Sending (Brevo by default): call openmeru_dns_records for the DNS and the sender; then save the relay either in {$url}/admin → Settings → Sending, or:
   cd {$dir} && php artisan openmeru:smtp smtp-relay.brevo.com 587 'login@smtp-brevo.com' - hello@{$domain}
   (the "-" asks for the SMTP key without echo; the system sender must be on {$domain}).

6. Receiving: call openmeru_cloudflare_worker with domain={$domain}, url={$url}, gmail=<the otp/catch-all Gmail> and follow its steps (Email Routing → destination address → Worker → variables → routing rule).

7. Test: open {$url}/admin, sign in (the 6-digit code arrives in the Gmail), create a mailbox, sign in at {$url}/ and exchange a message with the Gmail. The reply shows up in seconds.

8. Updates are automatic (daily check of {$base}/version). Manual: cd {$dir} && php artisan openmeru:update   or the install line again.

Notes: php may be 8.4 on the server while php8.5 exists; the installer picks the newest 8.5+ binary (override with OPENMERU_PHP=/usr/bin/php8.5). The web root must stay on public_root. Keep a backup of .env (APP_KEY encrypts the stored keys).
TXT;
    }

    private function worker(array $a): string
    {
        $domain = self::host((string) ($a['domain'] ?? ''), 'example.com');
        $url = trim((string) ($a['url'] ?? '')) ?: 'https://'.$domain;
        $gmail = trim((string) ($a['gmail'] ?? '')) ?: 'yourproject@gmail.com';
        $code = (string) @file_get_contents(base_path('cloudflare-email-worker.js'));

        return <<<TXT
# Cloudflare: receiving mail for {$domain}

a. Email Routing: Cloudflare dashboard → the domain → Email → Email Routing → Enable (let it add the MX records).
   Destination addresses → Add → {$gmail} → confirm the verification email in that Gmail.
b. Worker: Workers & Pages → Create → Worker → name openmeru-mail-inbound → Deploy. Edit code → replace everything with the source below → Deploy.
c. Variables: the Worker → Settings → Variables and Secrets →
   - Text   GMAIL          = {$gmail}
   - Text   INBOUND_URL    = {$url}/hooks/inbound
   - Secret INBOUND_SECRET = the value printed by the installer (same as INBOUND_SECRET in the install's .env)
d. Route: Email Routing → Routing rules → Catch-all address → Edit → action "Send to a Worker" → openmeru-mail-inbound → enable.
   If the domain already forwards mail elsewhere and the catch-all must stay, add one rule per mailbox instead: name@{$domain} → Send to a Worker → openmeru-mail-inbound.
   Every message still reaches {$gmail}; addresses without a mailbox stay in Gmail only.

Check: Workers → openmeru-mail-inbound → Logs. A 403 means the secret differs between the Worker and the .env.

## cloudflare-email-worker.js
{$code}
TXT;
    }

    private function dns(array $a): string
    {
        $domain = self::host((string) ($a['domain'] ?? ''), 'example.com');
        $provider = (string) ($a['provider'] ?? 'brevo');
        $spfInclude = match ($provider) {
            'smtp2go' => 'include:spf.smtp2go.com',
            'mailgun' => 'include:mailgun.org',
            'postmark' => 'include:spf.mtasv.net',
            'sendgrid' => 'include:sendgrid.net',
            'ses' => 'include:amazonses.com',
            'resend' => 'include:amazonses.com',
            'custom' => 'include:<your relay\'s SPF include>',
            default => 'include:spf.brevo.com',
        };
        $brevo = $provider === 'brevo' ? <<<TXT

Brevo (sending):
1. brevo.com → Senders, Domains & Dedicated IPs → Domains → Add a domain → {$domain}.
2. Brevo shows records (a Brevo code TXT, DKIM CNAME/TXT, DMARC TXT): add each in Cloudflare → DNS → Records, with the proxy OFF (grey cloud) on CNAMEs. Click Verify in Brevo.
3. Senders → add a sender on the domain, e.g. hello@{$domain} (the install's system sender must be on {$domain}).
4. SMTP & API → SMTP → Generate a new SMTP key → copy the login (xxxx@smtp-brevo.com) and the key → save them in /admin → Settings → Sending (or php artisan openmeru:smtp ... with "-" to be prompted for the key).
TXT : '';

        return <<<TXT
# DNS for {$domain} (all records live in Cloudflare → DNS → Records)

- MX: added automatically when Email Routing is enabled (route1/2/3.mx.cloudflare.net). Do not add other MX records.
- SPF: exactly ONE TXT record at the root ({$domain}) merging Cloudflare and the relay:
    v=spf1 include:_spf.mx.cloudflare.net {$spfInclude} ~all
  Two SPF records make every receiver reject the domain's mail.
- DKIM: the CNAME/TXT record(s) the relay gives you (Brevo: under Domains → Authenticate). Proxy OFF.
- DMARC: TXT at _dmarc.{$domain}, e.g.  v=DMARC1; p=none; rua=mailto:dmarc@{$domain}  (tighten to quarantine later).
- The site itself: an A/AAAA (or CNAME) record for the install's host (e.g. mail.{$domain}) pointing at the server; proxied is fine.
{$brevo}
TXT;
    }

    private function commands(): string
    {
        return <<<'TXT'
Run inside the app folder (cd ~/openmeru-app). Use the PHP 8.5 binary if plain php is older (php8.5 artisan ...).

- php artisan openmeru:install            Interactive setup; re-runnable, keeps what is set. Flags: --domain= --url= --otp-email= --db-name= --db-user= --admin-email= --admin-name= --skip-admin --skip-smtp --no-interaction
- php artisan openmeru:admin EMAIL NAME   Create or reset an admin mailbox (asks the password without echo; pass it as a third argument only in scripts).
- php artisan openmeru:smtp HOST PORT LOGIN -  FROM   Save the SMTP relay; "-" prompts for the key. FROM must be on the mail domain.
- php artisan openmeru:domain DOMAIN      Switch the install to another mail domain (MAIL_DOMAIN).
- php artisan openmeru:update             Update to the latest published version (backup, code via git or package, composer, migrate, caches). --package forces the tarball; --auto only runs when a newer version is out.
- php artisan openmeru:secret --rotate    (if present) Rotate INBOUND_SECRET; update the Worker secret afterwards.
- php artisan migrate --force             Apply pending migrations by hand.
- php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache   Rebuild caches after editing .env.

Admin UI equivalents live at /admin → Settings: General (name, logo, icon, favicon, background, colours), Sending (relay presets), Push notifications, Translation, Incoming mail (inbound URL + secret status), Security (self sign-up, mandatory 2FA), Updates (auto-update toggle, check now).
TXT;
    }

    private function troubleshoot(string $symptom): string
    {
        $s = mb_strtolower($symptom);
        $all = [
            'mail not arriving / inbox empty' => "Check Workers → openmeru-mail-inbound → Logs and Email Routing → Activity log. 403 from the install = INBOUND_SECRET differs between the Worker secret and the .env (copy it again, then redeploy the Worker). 404 = INBOUND_URL wrong (must be <url>/hooks/inbound). No log at all = the routing rule is not pointing at the Worker or Email Routing is disabled. Also read storage/logs/laravel.log.",
            'admin sign-in code never arrives' => "The code is emailed through the SMTP relay to ADMIN_OTP_EMAIL. Causes: relay not configured (Settings → Sending or openmeru:smtp), system sender not on the mail domain (relays drop it), sender not verified at the relay, or ADMIN_OTP_EMAIL wrong in .env (then php artisan config:cache). Test with Settings → Sending → Test sending.",
            'update failed: git not found / HOME or COMPOSER_HOME' => "Fixed from 1.1.23/1.1.50: the updater downloads the release package when git is unusable and gives composer a HOME. Close the failed banner in /admin and run the update again, or run on the server: curl -fsSL https://openmeru.com/install | bash (works without git).",
            'update failed: local changes would be overwritten' => "The checkout has edits. Commit them (git) or discard them (git checkout -- .) and run the update again; config lives in .env and the database, never in tracked files.",
            'file upload refused (logo, background, attachment)' => "PHP's limits are below the app's. The app ships public_root/.user.ini (PHP-FPM) and .htaccess (mod_php). If the server ignores them, set upload_max_filesize=30M and post_max_size=64M in php.ini (or a conf.d file) and restart PHP; nginx also needs client_max_body_size 64m.",
            'locked out by two-factor' => "An admin resets it: /admin → Accounts → the mailbox → Reset two-factor. From the terminal: php artisan tinker, then App\\Models\\User::where('email','x@domain')->first()->forceFill(['two_factor_secret'=>null,'two_factor_confirmed_at'=>null,'two_factor_recovery_codes'=>null])->save();",
            'root user / permission errors' => "Run the installer and artisan as the site user (the owner of the app folder), never as root; the web server user must be able to write storage/ and bootstrap/cache (chown -R siteuser:siteuser, chmod -R u+rwX,g+rwX storage bootstrap/cache).",
            'php too old' => "OpenMeru needs PHP 8.5+. Install it (https://php.new/install/linux/8.5 or the distro's php8.5 packages) and run the installer with OPENMERU_PHP=/usr/bin/php8.5, or set the site's PHP version to 8.5 in CloudPanel.",
            'two spf records / mail marked as spam' => "Merge Cloudflare and the relay into one SPF TXT: v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all. Add DKIM and DMARC from the relay. Call openmeru_dns_records.",
            'landing page or demo shows on my install' => "Only openmeru.com shows the project pages. If an install does, MAIL_DOMAIN in .env is openmeru.com or OPENMERU_SOURCE=true; fix .env and php artisan config:cache.",
        ];
        $hits = array_filter($all, function ($v, $k) use ($s) {
            if ($s === '') {
                return true;
            }
            foreach (preg_split('/\W+/', $k) as $word) {
                if (strlen($word) > 3 && str_contains($s, strtolower($word))) {
                    return true;
                }
            }

            return str_contains(strtolower($v), $s);
        }, ARRAY_FILTER_USE_BOTH);
        if (! $hits) {
            $hits = $all;
        }
        $out = [];
        foreach ($hits as $k => $v) {
            $out[] = "## {$k}\n{$v}";
        }

        return implode("\n\n", $out);
    }

    private function v(): string
    {
        return Version::current();
    }
}
