@php($repo = 'https://github.com/WeslleyHarakawa/OpenMeru')
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OpenMeru: open-source email for your own domain</title>
  <meta name="description" content="OpenMeru Mail is an open-source webmail you run on your own server. Send with Brevo, receive with Cloudflare Email Routing, and keep a copy of everything in Gmail.">
  <meta property="og:title" content="OpenMeru: open-source email for your own domain">
  <meta property="og:description" content="Self-hosted webmail with an admin panel, 10 languages and push notifications. No mail server to run.">
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #fdfcfc; --bg2: #f5f3f2; --ink: #201d1d; --muted: #6b6566; --faint: #9a9394;
      --line: #e6e2e1; --code: #f1eeed; --accent: #2f6bff; --ok: #12a150;
    }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #0d0c0c; --bg2: #151313; --ink: #eeeaea; --muted: #a29b9c; --faint: #6f6869; --line: #272323; --code: #181616; --accent: #6ea8ff; --ok: #3ddc84; }
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.65 "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace; -webkit-font-smoothing: antialiased; }
    a { color: inherit; }
    .wrap { max-width: 960px; margin: 0 auto; padding: 0 20px; }
    .frame { border-left: 1px solid var(--line); border-right: 1px solid var(--line); }

    header.top { position: sticky; top: 0; z-index: 10; background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
    header.top .wrap { display: flex; align-items: center; justify-content: space-between; height: 60px; }
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; font-weight: 700; letter-spacing: -0.02em; }
    .brand svg { width: 26px; height: 26px; }
    nav.links { display: flex; gap: 22px; font-size: 14px; }
    nav.links a { text-decoration: none; color: var(--muted); }
    nav.links a:hover { color: var(--ink); }
    nav.links a.cta { color: var(--ink); font-weight: 600; }

    section { border-bottom: 1px solid var(--line); }
    .pad { padding: 64px 28px; }
    .hero { padding: 88px 28px 64px; }
    .tag { display: inline-flex; gap: 8px; align-items: center; font-size: 13px; color: var(--muted); border: 1px solid var(--line); border-radius: 999px; padding: 3px 12px; text-decoration: none; }
    .tag b { color: var(--ok); font-weight: 600; }
    h1 { font-size: clamp(30px, 5.4vw, 52px); line-height: 1.12; letter-spacing: -0.035em; margin: 22px 0 18px; font-weight: 700; }
    h1 span { color: var(--faint); }
    .lead { font-family: Inter, system-ui, sans-serif; font-size: 18px; color: var(--muted); max-width: 640px; margin: 0 0 34px; }

    .install { border: 1px solid var(--line); border-radius: 10px; background: var(--code); max-width: 680px; overflow: hidden; }
    .tabs { display: flex; border-bottom: 1px solid var(--line); overflow-x: auto; }
    .tabs button { font: inherit; font-size: 13px; background: none; border: 0; color: var(--muted); padding: 10px 16px; cursor: pointer; border-right: 1px solid var(--line); white-space: nowrap; }
    .tabs button[aria-selected="true"] { color: var(--ink); background: var(--bg); }
    .cmd { display: flex; align-items: center; gap: 12px; padding: 16px 18px; }
    .cmd code { flex: 1; overflow-x: auto; white-space: pre; font-size: 14px; }
    .cmd code::before { content: "$ "; color: var(--faint); }
    .copy { font: inherit; font-size: 12px; border: 1px solid var(--line); background: var(--bg); color: var(--muted); border-radius: 6px; padding: 5px 10px; cursor: pointer; flex-shrink: 0; }
    .copy:hover { color: var(--ink); }
    .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 26px; }
    .btn { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--line); }
    .btn.dark { background: var(--ink); color: var(--bg); border-color: var(--ink); }
    .btn svg { width: 17px; height: 17px; }

    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--faint); font-weight: 600; margin: 0 0 22px; }
    h3 { font-size: 22px; letter-spacing: -0.02em; margin: 0 0 10px; }
    p.txt { font-family: Inter, system-ui, sans-serif; color: var(--muted); margin: 0 0 14px; max-width: 700px; }

    .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 0; border-top: 1px solid var(--line); border-left: 1px solid var(--line); }
    .features div { padding: 22px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .features b { display: block; margin-bottom: 6px; }
    .features p { font-family: Inter, system-ui, sans-serif; color: var(--muted); margin: 0; font-size: 14px; }

    pre.flow { background: var(--code); border: 1px solid var(--line); border-radius: 10px; padding: 20px; overflow-x: auto; font-size: 13px; line-height: 1.55; color: var(--muted); margin: 0; }
    pre.flow b { color: var(--ink); font-weight: 600; }

    .need { width: 100%; border-collapse: collapse; font-size: 14px; }
    .need td { border-top: 1px solid var(--line); padding: 14px 10px 14px 0; vertical-align: top; }
    .need td:first-child { font-weight: 600; white-space: nowrap; width: 150px; }
    .need td:last-child { font-family: Inter, system-ui, sans-serif; color: var(--muted); }

    .step { display: grid; grid-template-columns: 56px 1fr; gap: 6px; padding: 30px 0; border-top: 1px solid var(--line); }
    .step:first-of-type { border-top: 0; padding-top: 0; }
    .step > div { min-width: 0; }
    .num { font-size: 13px; color: var(--faint); padding-top: 5px; }
    .step ol, .step ul { font-family: Inter, system-ui, sans-serif; color: var(--muted); padding-left: 20px; margin: 0 0 14px; }
    .step li { margin: 5px 0; }
    .step li b, .step p b { color: var(--ink); font-weight: 600; }
    code.i { font-family: "IBM Plex Mono", monospace; font-size: 13px; background: var(--code); border: 1px solid var(--line); border-radius: 4px; padding: 1px 5px; color: var(--ink); word-break: break-word; }
    .block { position: relative; background: var(--code); border: 1px solid var(--line); border-radius: 8px; margin: 12px 0 16px; }
    .block pre { margin: 0; padding: 14px 16px; overflow-x: auto; font-size: 13px; line-height: 1.6; }
    .block .copy { position: absolute; top: 8px; right: 8px; }
    .note { font-family: Inter, system-ui, sans-serif; font-size: 14px; border-left: 2px solid var(--accent); padding: 8px 14px; color: var(--muted); background: var(--bg2); margin: 12px 0; }
    .vars { width: 100%; border-collapse: collapse; font-size: 13px; margin: 8px 0 14px; }
    .vars th, .vars td { text-align: left; border: 1px solid var(--line); padding: 8px 10px; }
    .vars th { color: var(--faint); font-weight: 500; }

    details { border-top: 1px solid var(--line); padding: 16px 0; }
    details summary { cursor: pointer; font-weight: 600; list-style: none; }
    details summary::before { content: "+ "; color: var(--faint); }
    details[open] summary::before { content: "− "; }
    details p { font-family: Inter, system-ui, sans-serif; color: var(--muted); margin: 10px 0 0; }

    footer .wrap { display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; padding-top: 26px; padding-bottom: 40px; font-size: 13px; color: var(--faint); }
    footer a { text-decoration: none; }
    footer a:hover { color: var(--ink); }

    @media (max-width: 640px) {
      nav.links a.hide-sm { display: none; }
      .pad, .hero { padding-left: 16px; padding-right: 16px; }
      .hero { padding-top: 56px; }
      .step { grid-template-columns: minmax(0, 1fr); }
      h3 { font-size: 19px; }
      .install { max-width: 100%; }
      .vars { display: block; overflow-x: auto; }
      .wrap { padding: 0; }
      header.top .wrap, footer .wrap { padding-left: 16px; padding-right: 16px; }
      .need td:first-child { width: auto; white-space: normal; }
    }
  </style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="brand" href="/">
      <svg viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="lg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6ea8ff"/><stop offset="100%" stop-color="#1d57f5"/></linearGradient></defs><rect width="64" height="64" rx="18" fill="url(#lg)"/><path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/><path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/></svg>
      openmeru
    </a>
    <nav class="links">
      <a class="hide-sm" href="#how">How it works</a>
      <a class="hide-sm" href="#install">Install</a>
      <a href="{{ $repo }}" target="_blank" rel="noopener">GitHub</a>
      <a class="cta" href="/mail">Sign in →</a>
    </nav>
  </div>
</header>

<main class="wrap frame">
  <section class="hero">
    <a class="tag" href="{{ $repo }}" target="_blank" rel="noopener"><b>●</b> Open source · MIT</a>
    <h1>Email on your own domain.<br><span>Without running a mail server.</span></h1>
    <p class="lead">OpenMeru Mail is a self-hosted webmail. You get an admin panel, mailboxes, a rich editor, push notifications and 10 languages. Send with Brevo, receive with Cloudflare, and keep a copy of everything in Gmail.</p>

    <div class="install">
      <div class="tabs" role="tablist">
        <button role="tab" aria-selected="true" data-cmd="git clone {{ $repo }}.git openmeru-app">git</button>
        <button role="tab" aria-selected="false" data-cmd="curl -L {{ $repo }}/archive/refs/heads/main.zip -o openmeru.zip">zip</button>
        <button role="tab" aria-selected="false" data-cmd="cd openmeru-app && composer install --no-dev && cp .env.example .env && php artisan key:generate">setup</button>
      </div>
      <div class="cmd"><code id="cmd">git clone {{ $repo }}.git openmeru-app</code><button class="copy" data-copy="#cmd">copy</button></div>
    </div>

    <div class="actions">
      <a class="btn dark" href="{{ $repo }}" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg> View on GitHub</a>
      <a class="btn" href="{{ $repo }}/archive/refs/heads/main.zip">Download .zip</a>
      <a class="btn" href="#install">Read the install guide</a>
    </div>
  </section>

  <section class="pad">
    <h2>What you get</h2>
    <div class="features">
      <div><b>Admin panel</b><p>Create, search, open and delete <code class="i">you@yourdomain.com</code> mailboxes at <code class="i">/admin</code>. Admins sign in with a password plus a code sent by email.</p></div>
      <div><b>Private mailboxes</b><p>Each mailbox sees only its own mail. Inbox, sent, drafts, starred, archive, spam and trash.</p></div>
      <div><b>Rich editor</b><p>Formatting, Cc/Bcc, attachments up to 25 MB, signatures, replies and auto-forwarding.</p></div>
      <div><b>Push notifications</b><p>Get notified of new mail in the browser and on mobile. Inboxes update on their own.</p></div>
      <div><b>10 languages</b><p>English, Português (BR and PT), Español, Français, Deutsch, Italiano, Nederlands, Русский, 中文.</p></div>
      <div><b>No mail server</b><p>No Postfix, no IMAP, no port 25. Just PHP, MySQL and two free services.</p></div>
    </div>
  </section>

  <section class="pad" id="how">
    <h2>How it works</h2>
    <pre class="flow"><b>sending</b>
  webmail ──SMTP──▶ <b>Brevo</b> ──▶ recipient

<b>receiving</b>
  sender ──▶ <b>Cloudflare Email Routing</b> (catch-all *@yourdomain.com)
                    │
                    ▼
              <b>Email Worker</b> ──────────▶ <b>Gmail</b>   (backup copy of every message)
                    │
                    │  POST /hooks/inbound  + shared secret
                    ▼
              <b>OpenMeru Mail</b> ─────────▶ the recipient's mailbox</pre>
    <p class="txt" style="margin-top:18px">Brevo delivers what you send. Cloudflare accepts what you receive and passes it to a small Worker. The Worker always sends a copy to your Gmail, then delivers the message to your server. If your server is down, nothing is lost.</p>
  </section>

  <section class="pad">
    <h2>What you need</h2>
    <table class="need">
      <tr><td>A server</td><td>A Linux VPS with PHP 8.3+ (8.5 recommended), MySQL 8 or MariaDB, Composer and nginx or Apache. <a href="https://www.cloudpanel.io" target="_blank" rel="noopener">CloudPanel</a> makes it easy.</td></tr>
      <tr><td>A domain</td><td>Its DNS must be on <a href="https://www.cloudflare.com" target="_blank" rel="noopener">Cloudflare</a>. The free plan is enough.</td></tr>
      <tr><td>Brevo</td><td>Sends your mail. The free plan includes 300 emails per day. <a href="https://www.brevo.com" target="_blank" rel="noopener">brevo.com</a></td></tr>
      <tr><td>A Gmail account</td><td>Receives a copy of every incoming message (the catch-all) and the admin sign-in codes. Create one just for the project.</td></tr>
    </table>
  </section>

  <section class="pad" id="install">
    <h2>Install, step by step</h2>

    <div class="step">
      <div class="num">01</div>
      <div>
        <h3>Create a Gmail for the catch-all</h3>
        <p class="txt">Create a new Gmail account, for example <code class="i">yourproject@gmail.com</code>. It does two jobs:</p>
        <ul>
          <li>It's the <b>Cloudflare destination address</b>, which gets a copy of every message sent to any address on your domain.</li>
          <li>It's the <b>ADMIN_OTP_EMAIL</b>, which gets the 6-digit code each time someone signs in to <code class="i">/admin</code>.</li>
        </ul>
      </div>
    </div>

    <div class="step">
      <div class="num">02</div>
      <div>
        <h3>Install the app on your server</h3>
        <p class="txt">Sign in to your server over SSH as the site user (in CloudPanel, the user of the site you created for your domain). Then run:</p>
        <div class="block"><button class="copy">copy</button><pre>cd ~
git clone {{ $repo }}.git openmeru-app
cd openmeru-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate</pre></div>
        <p class="txt">Create a MySQL database (CloudPanel: <b>Databases → Add Database</b>). Then edit <code class="i">.env</code>:</p>
        <div class="block"><button class="copy">copy</button><pre>APP_URL=https://yourdomain.com
MAIL_DOMAIN=yourdomain.com
INBOUND_SECRET=        # output of: openssl rand -hex 24
ADMIN_OTP_EMAIL=yourproject@gmail.com
DB_DATABASE=openmeru
DB_USERNAME=openmeru
DB_PASSWORD=your-db-password</pre></div>
        <p class="txt">Create the tables, your domain, the first admin and the push-notification keys:</p>
        <div class="block"><button class="copy">copy</button><pre>php artisan migrate --force
php artisan openmeru:domain yourdomain.com
php artisan openmeru:admin admin@yourdomain.com "Administrator" 'a-strong-password'
php artisan tinker --execute='App\Support\Push::generateKeys();'
php artisan config:cache && php artisan route:cache && php artisan view:cache</pre></div>
        <p class="txt">Point your web server at the <code class="i">public_root</code> folder. Either set the site's document root to <code class="i">~/openmeru-app/public_root</code>, or on CloudPanel copy the files into the site folder:</p>
        <div class="block"><button class="copy">copy</button><pre>cp -a ~/openmeru-app/public_root/. ~/htdocs/yourdomain.com/</pre></div>
        <div class="note">Turn on HTTPS. Let's Encrypt in CloudPanel and the Cloudflare proxy both work. <code class="i">storage/</code> and <code class="i">bootstrap/cache/</code> must be writable. When you're done, <code class="i">https://yourdomain.com/mail</code> shows the sign-in page.</div>
      </div>
    </div>

    <div class="step">
      <div class="num">03</div>
      <div>
        <h3>Brevo, for sending</h3>
        <ol>
          <li>Create an account at <a href="https://www.brevo.com" target="_blank" rel="noopener">brevo.com</a>.</li>
          <li>Go to <b>Senders, Domains &amp; Dedicated IPs → Domains → Add a domain</b> and enter <code class="i">yourdomain.com</code>.</li>
          <li>Brevo shows DNS records: a Brevo code, DKIM and DMARC. Add each one in <b>Cloudflare → DNS → Records</b>, with the proxy <b>off</b> (grey cloud) on CNAMEs. Then click <b>Verify</b> in Brevo.</li>
          <li>Add a sender: <code class="i">hello@yourdomain.com</code>.</li>
          <li>Go to <b>SMTP &amp; API → SMTP → Generate a new SMTP key</b>. Copy the <b>login</b> (<code class="i">xxxx@smtp-brevo.com</code>) and the <b>key</b>.</li>
          <li>Save them in <b>/admin → Settings → SMTP</b>, or from the terminal:</li>
        </ol>
        <div class="block"><button class="copy">copy</button><pre>php artisan openmeru:smtp smtp-relay.brevo.com 587 'login@smtp-brevo.com' 'your-smtp-key' hello@yourdomain.com</pre></div>
        <div class="note"><b>One SPF record only.</b> Cloudflare and Brevo both need SPF, so merge them into one TXT record:<br><code class="i">v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all</code></div>
      </div>
    </div>

    <div class="step">
      <div class="num">04</div>
      <div>
        <h3>Cloudflare, for receiving</h3>
        <p class="txt"><b>a. Turn on Email Routing.</b></p>
        <ol>
          <li>Open your domain in Cloudflare, then go to <b>Email → Email Routing → Enable</b>. Let it add its MX records.</li>
          <li>Go to <b>Destination addresses → Add</b> and enter your Gmail. Confirm the verification email in Gmail.</li>
        </ol>
        <p class="txt"><b>b. Create the Email Worker.</b></p>
        <ol>
          <li>Go to <b>Workers &amp; Pages → Create → Worker</b>, name it <code class="i">openmeru-mail-inbound</code>, then click <b>Deploy</b>.</li>
          <li>Click <b>Edit code</b> and paste the contents of <a href="{{ $repo }}/blob/main/cloudflare-email-worker.js" target="_blank" rel="noopener"><code class="i">cloudflare-email-worker.js</code></a>. Then click <b>Deploy</b>.</li>
          <li>Go to <b>Settings → Variables and Secrets</b> and add:</li>
        </ol>
        <table class="vars">
          <tr><th>Type</th><th>Name</th><th>Value</th></tr>
          <tr><td>Text</td><td><code class="i">GMAIL</code></td><td>yourproject@gmail.com</td></tr>
          <tr><td>Text</td><td><code class="i">INBOUND_URL</code></td><td>https://yourdomain.com/hooks/inbound</td></tr>
          <tr><td>Secret</td><td><code class="i">INBOUND_SECRET</code></td><td>the same value as in .env</td></tr>
        </table>
        <p class="txt"><b>c. Send the catch-all to the Worker.</b> Go to <b>Email Routing → Routing rules → Catch-all address → Edit</b>. Set the action to <b>Send to a Worker</b>, pick <code class="i">openmeru-mail-inbound</code>, and turn it on.</p>
        <div class="note">Now every <code class="i">*@yourdomain.com</code> message goes to Gmail and to the matching mailbox. Addresses with no mailbox stay in Gmail only.</div>
      </div>
    </div>

    <div class="step">
      <div class="num">05</div>
      <div>
        <h3>Test it</h3>
        <ol>
          <li>Open <code class="i">https://yourdomain.com/admin</code> and sign in. The code arrives in your Gmail.</li>
          <li>Create a mailbox, for example <code class="i">test@yourdomain.com</code>.</li>
          <li>Sign in at <code class="i">/mail</code> and send a message to your Gmail.</li>
          <li>Reply from Gmail. The reply shows up in the inbox within seconds.</li>
        </ol>
        <div class="note">If mail doesn't arrive, check <b>Workers → openmeru-mail-inbound → Logs</b> (a 403 means the secrets don't match), the Email Routing <b>Activity log</b>, and <code class="i">storage/logs/laravel.log</code>.</div>
      </div>
    </div>
  </section>

  <section class="pad">
    <h2>Updating</h2>
    <div class="block"><button class="copy">copy</button><pre>cd ~/openmeru-app && git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
cp -a public_root/. ~/htdocs/yourdomain.com/   # only if you copy files (CloudPanel)</pre></div>
  </section>

  <section class="pad">
    <h2>FAQ</h2>
    <details><summary>Do I need to open port 25 or run Postfix?</summary><p>No. Brevo sends and Cloudflare receives. Your server only serves HTTPS.</p></details>
    <details><summary>Can I use IMAP or Outlook?</summary><p>Not yet. OpenMeru is a webmail. For a desktop client, use auto-forwarding to another inbox, or the Gmail copy.</p></details>
    <details><summary>Is it free?</summary><p>The software is MIT-licensed. Brevo's free plan sends 300 emails per day, and Cloudflare Email Routing and Workers are free for normal use.</p></details>
    <details><summary>Where are my passwords and keys stored?</summary><p>Mailbox passwords are hashed. The SMTP and API keys are encrypted with your <code class="i">APP_KEY</code>. Nothing secret goes in git, so back up your <code class="i">.env</code>.</p></details>
    <details><summary>Can I use another SMTP provider?</summary><p>Yes. Any SMTP relay that lets you send as your domain works. Set it in /admin → Settings.</p></details>
  </section>
</main>

<footer>
  <div class="wrap">
    <span>© {{ date('Y') }} OpenMeru · MIT · by Weslley Harakawa</span>
    <span style="display:flex;gap:18px">
      <a href="{{ $repo }}" target="_blank" rel="noopener">GitHub</a>
      <a href="/mail">Webmail</a>
      <a href="{{ route('privacy') }}">Privacy</a>
      <a href="{{ route('terms') }}">Terms</a>
    </span>
  </div>
</footer>

<script>
  document.querySelectorAll(".tabs button").forEach(function (b) {
    b.addEventListener("click", function () {
      document.querySelectorAll(".tabs button").forEach(function (x) { x.setAttribute("aria-selected", x === b ? "true" : "false"); });
      document.getElementById("cmd").textContent = b.dataset.cmd;
    });
  });
  document.querySelectorAll(".copy").forEach(function (b) {
    b.addEventListener("click", function () {
      var el = b.dataset.copy ? document.querySelector(b.dataset.copy) : b.parentElement.querySelector("pre");
      var text = el.textContent.replace(/\s+#.*$/gm, "");
      navigator.clipboard.writeText(text).then(function () { b.textContent = "copied"; setTimeout(function () { b.textContent = "copy"; }, 1500); });
    });
  });
</script>
</body>
</html>
