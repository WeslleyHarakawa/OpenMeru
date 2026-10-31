@php
  $repo = \App\Http\Controllers\SeoController::REPO;
  $locale = app()->getLocale();
  $canonical = \App\Http\Controllers\SeoController::pageUrl('en');
  $demoEmail = 'demo@'.config('inbound.domain');
  $version = \App\Support\Version::current();
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag($locale) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('landing.title') }}</title>
  <meta name="description" content="{{ __('landing.metaDescription') }}">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="{{ $canonical }}">
  @foreach (\App\Support\Locales::ALL as $code => [, $tag])
  <link rel="alternate" hreflang="{{ $tag }}" href="{{ \App\Http\Controllers\SeoController::pageUrl($code) }}">
  @endforeach
  <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="OpenMeru">
  <meta property="og:url" content="{{ \App\Http\Controllers\SeoController::pageUrl($locale) }}">
  <meta property="og:title" content="{{ __('landing.title') }}">
  <meta property="og:description" content="{{ __('landing.ogDescription') }}">
  <meta property="og:image" content="{{ url('/assets/icon-192.png') }}">
  <meta property="og:locale" content="{{ str_replace('-', '_', \App\Support\Locales::tag($locale)) }}">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="{{ __('landing.title') }}">
  <meta name="twitter:description" content="{{ __('landing.ogDescription') }}">
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/assets/icon-192.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous" rel="stylesheet">
  <script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'OpenMeru Mail',
    'applicationCategory' => 'CommunicationApplication',
    'operatingSystem' => 'Linux',
    'softwareVersion' => $version,
    'description' => __('landing.metaDescription'),
    'url' => $canonical,
    'downloadUrl' => $repo.'/archive/refs/heads/main.zip',
    'codeRepository' => $repo,
    'license' => 'https://opensource.org/licenses/MIT',
    'inLanguage' => array_map(fn ($l) => $l[1], array_values(\App\Support\Locales::ALL)),
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
    'author' => ['@type' => 'Person', 'name' => 'Weslley Harakawa', 'url' => 'https://www.linkedin.com/in/weslleyharakawa'],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
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
    header.top .wrap { display: flex; align-items: center; justify-content: space-between; height: 60px; gap: 12px; }
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; font-weight: 700; letter-spacing: -0.02em; }
    .brand svg { width: 26px; height: 26px; }
    nav.links { display: flex; gap: 22px; font-size: 14px; align-items: center; }
    nav.links a { text-decoration: none; color: var(--muted); }
    nav.links a:hover { color: var(--ink); }
    .lang { position: relative; }
    .lang-btn { font: inherit; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--line); background: var(--bg); color: var(--ink); cursor: pointer; }
    .lang-btn:hover { border-color: var(--accent); }
    .lang-btn .bi { font-size: 16px; line-height: 1; }
    .lang-code { font-size: 12px; font-weight: 700; letter-spacing: .04em; }
    .lang-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); z-index: 20; width: 320px; max-width: calc(100vw - 24px); padding: 6px; border-radius: 12px; border: 1px solid var(--line); background: var(--bg); box-shadow: 0 12px 32px rgba(0,0,0,.18); grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 4px; }
    .lang-menu.show { display: grid; }
    .lang-menu a { display: block; text-decoration: none; color: var(--ink); font-family: Inter, system-ui, sans-serif; font-size: 14px; padding: 7px 12px; border-radius: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lang-menu a:hover { background: var(--code); }
    .lang-menu a.active { background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); font-weight: 600; }

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
    .cmd code { flex: 1; min-width: 0; overflow-x: auto; white-space: pre; font-size: 14px; scrollbar-width: none; }
    .cmd code::-webkit-scrollbar { display: none; }
    .block pre, pre.flow, .vars { scrollbar-width: thin; scrollbar-color: var(--line) transparent; }
    .demos { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
    .demo-card { display: block; text-decoration: none; border: 1px solid var(--line); border-radius: 10px; padding: 20px; background: var(--code); transition: border-color .15s; }
    .demo-card:hover { border-color: var(--accent); }
    .demo-card b { display: block; font-size: 18px; margin: 6px 0 8px; }
    .demo-card p { font-family: Inter, system-ui, sans-serif; color: var(--muted); margin: 0 0 14px; font-size: 14px; }
    .demo-kicker { font-size: 12px; color: var(--faint); }
    .demo-go { color: var(--accent); font-weight: 600; font-size: 14px; }
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
    .features div { padding: 22px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
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

    footer .wrap { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px 24px; padding-top: 26px; padding-bottom: 40px; font-size: 12px; color: var(--faint); white-space: nowrap; }
    footer a { text-decoration: underline; }
    footer a:hover { color: var(--ink); }
    footer .r { display: flex; gap: 18px; justify-content: flex-end; flex-wrap: wrap; }

    @media (max-width: 640px) {
      nav.links a.hide-sm { display: none; }
      nav.links { gap: 14px; }
      .lang-code { display: none; }
      .lang-btn { width: 36px; padding: 0; justify-content: center; }
      .pad, .hero { padding-left: 16px; padding-right: 16px; }
      .hero { padding-top: 56px; }
      .step { grid-template-columns: minmax(0, 1fr); }
      h3 { font-size: 19px; }
      .install { max-width: 100%; }
      .vars { display: block; overflow-x: auto; }
      .wrap { padding: 0; }
      header.top .wrap, footer .wrap { padding-left: 16px; padding-right: 16px; }
      footer .wrap { flex-direction: column; text-align: center; white-space: normal; }
      footer .r { justify-content: center; }
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
      <a href="#demo">{{ __('landing.navDemo') }}</a>
      <a class="hide-sm" href="#how">{{ __('landing.navHow') }}</a>
      <a class="hide-sm" href="#install">{{ __('landing.navInstall') }}</a>
      <a class="hide-sm" href="{{ $repo }}" target="_blank" rel="noopener">GitHub</a>
      <div class="lang" id="langPick">
        <button class="lang-btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('landing.language') }}">
          <i class="bi bi-globe2"></i><span class="lang-code">{{ strtoupper(str_replace('_', '-', $locale)) }}</span>
        </button>
        <div class="lang-menu" role="menu">
          @foreach (\App\Support\Locales::ALL as $code => [$native, $tag])
          <a role="menuitem" href="/?lang={{ $code }}" lang="{{ $tag }}" hreflang="{{ $tag }}" @class(['active' => $code === $locale])>{{ $native }}</a>
          @endforeach
        </div>
      </div>
    </nav>
  </div>
</header>

<main class="wrap frame">
  <section class="hero">
    <a class="tag" href="{{ $repo }}" target="_blank" rel="noopener"><b>●</b> {{ __('landing.tag') }}</a>
    <h1>{{ __('landing.h1a') }}<br><span>{{ __('landing.h1b') }}</span></h1>
    <p class="lead">{{ __('landing.lead') }}</p>

    <div class="install">
      <div class="tabs" role="tablist">
        <button role="tab" aria-selected="true" data-cmd="curl -fsSL https://openmeru.com/install | bash">curl</button>
        <button role="tab" aria-selected="false" data-cmd="git clone {{ $repo }}.git openmeru-app && cd openmeru-app && composer install --no-dev && php artisan openmeru:install">git</button>
        <button role="tab" aria-selected="false" data-cmd="curl -L {{ $repo }}/archive/refs/heads/main.zip -o openmeru.zip">zip</button>
      </div>
      <div class="cmd"><code id="cmd">curl -fsSL https://openmeru.com/install | bash</code><button class="copy" data-copy="#cmd">{{ __('landing.copy') }}</button></div>
    </div>

    <div class="actions">
      <a class="btn dark" href="{{ $repo }}" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg> {{ __('landing.viewGithub') }}</a>
      <a class="btn" href="#demo">{{ __('landing.tryDemo') }}</a>
      <a class="btn" href="{{ $repo }}/archive/refs/heads/main.zip">{{ __('landing.downloadZip') }}</a>
      <a class="btn" href="#install">{{ __('landing.readGuide') }}</a>
    </div>
  </section>

  <section class="pad">
    <h2>{{ __('landing.whatYouGet') }}</h2>
    <div class="features">
      @foreach (['f1', 'f2', 'f3', 'f4', 'f5', 'f6'] as $f)
      <div><b>{{ __('landing.'.$f.'t') }}</b><p>{!! __('landing.'.$f) !!}</p></div>
      @endforeach
    </div>
  </section>

  <section class="pad" id="demo">
    <h2>{{ __('landing.demoTitle') }}</h2>
    <p class="txt">{{ __('landing.demoLead') }}</p>
    <div class="demos">
      <a class="demo-card" href="/demo">
        <span class="demo-kicker">/demo</span>
        <b>{{ __('landing.demoMailbox') }}</b>
        <p>{!! __('landing.demoMailboxText', ['email' => e($demoEmail)]) !!}</p>
        <span class="demo-go">{{ __('landing.demoMailboxGo') }}</span>
      </a>
      <a class="demo-card" href="/demo/admin">
        <span class="demo-kicker">/demo/admin</span>
        <b>{{ __('landing.demoAdmin') }}</b>
        <p>{!! __('landing.demoAdminText') !!}</p>
        <span class="demo-go">{{ __('landing.demoAdminGo') }}</span>
      </a>
    </div>
  </section>

  <section class="pad" id="how">
    <h2>{{ __('landing.howTitle') }}</h2>
    <pre class="flow"><b>{{ __('landing.flowSending') }}</b>
  webmail ──SMTP──▶ <b>Brevo</b> ──▶ {{ __('landing.flowRecipient') }}

<b>{{ __('landing.flowReceiving') }}</b>
  {{ __('landing.flowSender') }} ──▶ <b>Cloudflare Email Routing</b> ({{ __('landing.flowCatchAll') }})
                    │
                    ▼
              <b>Email Worker</b> ──────────▶ <b>Gmail</b>   ({{ __('landing.flowBackup') }})
                    │
                    │  POST /hooks/inbound  + {{ __('landing.flowSecret') }}
                    ▼
              <b>OpenMeru Mail</b> ─────────▶ {{ __('landing.flowMailbox') }}</pre>
    <p class="txt" style="margin-top:18px">{{ __('landing.howText') }}</p>
  </section>

  <section class="pad">
    <h2>{{ __('landing.needTitle') }}</h2>
    <table class="need">
      <tr><td>{{ __('landing.needServer') }}</td><td>{!! __('landing.needServerText') !!}</td></tr>
      <tr><td>{{ __('landing.needDomain') }}</td><td>{!! __('landing.needDomainText') !!}</td></tr>
      <tr><td>{{ __('landing.needBrevo') }}</td><td>{!! __('landing.needBrevoText') !!}</td></tr>
      <tr><td>{{ __('landing.needGmail') }}</td><td>{!! __('landing.needGmailText') !!}</td></tr>
    </table>
  </section>

  <section class="pad" id="install">
    <h2>{{ __('landing.installTitle') }}</h2>

    <div class="step">
      <div class="num">01</div>
      <div>
        <h3>{{ __('landing.s1t') }}</h3>
        <p class="txt">{!! __('landing.s1p') !!}</p>
        <ul>
          <li>{!! __('landing.s1a') !!}</li>
          <li>{!! __('landing.s1b') !!}</li>
        </ul>
      </div>
    </div>

    <div class="step">
      <div class="num">02</div>
      <div>
        <h3>{{ __('landing.s2t') }}</h3>
        <p class="txt">{!! __('landing.s2p1') !!}</p>
        <div class="block"><button class="copy">{{ __('landing.copy') }}</button><pre>curl -fsSL https://openmeru.com/install | bash</pre></div>
        <p class="txt">{!! __('landing.s2p2') !!}</p>
        <p class="txt">{{ __('landing.s2p3') }}</p>
        <div class="block"><button class="copy">{{ __('landing.copy') }}</button><pre>git clone {{ $repo }}.git openmeru-app
cd openmeru-app
composer install --no-dev --optimize-autoloader
php artisan openmeru:install</pre></div>
        <p class="txt">{!! __('landing.s2p4') !!}</p>
        <div class="note">{!! __('landing.s2note') !!}</div>
      </div>
    </div>

    <div class="step">
      <div class="num">03</div>
      <div>
        <h3>{{ __('landing.s3t') }}</h3>
        <ol>
          <li>{!! __('landing.s3a') !!}</li>
          <li>{!! __('landing.s3b') !!}</li>
          <li>{!! __('landing.s3c') !!}</li>
          <li>{!! __('landing.s3d') !!}</li>
          <li>{!! __('landing.s3e') !!}</li>
          <li>{!! __('landing.s3f') !!}</li>
        </ol>
        <div class="block"><button class="copy">{{ __('landing.copy') }}</button><pre>php artisan openmeru:smtp smtp-relay.brevo.com 587 'login@smtp-brevo.com' - hello@yourdomain.com</pre></div>
        <div class="note">{!! __('landing.s3note') !!}</div>
      </div>
    </div>

    <div class="step">
      <div class="num">04</div>
      <div>
        <h3>{{ __('landing.s4t') }}</h3>
        <p class="txt"><b>{{ __('landing.s4a') }}</b></p>
        <ol>
          <li>{!! __('landing.s4a1') !!}</li>
          <li>{!! __('landing.s4a2') !!}</li>
        </ol>
        <p class="txt"><b>{{ __('landing.s4b') }}</b></p>
        <ol>
          <li>{!! __('landing.s4b1') !!}</li>
          <li>{!! __('landing.s4b2', ['repo' => $repo]) !!}</li>
          <li>{!! __('landing.s4b3') !!}</li>
        </ol>
        <table class="vars">
          <tr><th>{{ __('landing.varType') }}</th><th>{{ __('landing.varName') }}</th><th>{{ __('landing.varValue') }}</th></tr>
          <tr><td>{{ __('landing.varText') }}</td><td><code class="i">GMAIL</code></td><td>yourproject@gmail.com</td></tr>
          <tr><td>{{ __('landing.varText') }}</td><td><code class="i">INBOUND_URL</code></td><td>https://yourdomain.com/hooks/inbound</td></tr>
          <tr><td>{{ __('landing.varSecret') }}</td><td><code class="i">INBOUND_SECRET</code></td><td>{{ __('landing.varSecretValue') }}</td></tr>
        </table>
        <p class="txt"><b>{{ __('landing.s4c') }}</b></p>
        <ul>
          <li>{!! __('landing.s4c1') !!}</li>
          <li>{!! __('landing.s4c2') !!}</li>
        </ul>
        <div class="note">{!! __('landing.s4note') !!}</div>
      </div>
    </div>

    <div class="step">
      <div class="num">05</div>
      <div>
        <h3>{{ __('landing.s5t') }}</h3>
        <ol>
          <li>{!! __('landing.s5a') !!}</li>
          <li>{!! __('landing.s5b') !!}</li>
          <li>{!! __('landing.s5c') !!}</li>
          <li>{!! __('landing.s5d') !!}</li>
        </ol>
        <div class="note">{!! __('landing.s5note') !!}</div>
      </div>
    </div>
  </section>

  <section class="pad">
    <h2>{{ __('landing.updatingTitle') }}</h2>
    <p class="txt">{!! __('landing.updatingText') !!}</p>
    <div class="block"><button class="copy">{{ __('landing.copy') }}</button><pre>curl -fsSL https://openmeru.com/install | bash</pre></div>
  </section>

  <section class="pad">
    <h2>{{ __('landing.faqTitle') }}</h2>
    @foreach ([1, 2, 3, 4, 5, 6] as $n)
    <details><summary>{{ __('landing.q'.$n) }}</summary><p>{!! __('landing.a'.$n) !!}</p></details>
    @endforeach
  </section>
</main>

<footer>
  <div class="wrap">
    <span>© {{ date('Y') }} <a href="https://openmeru.com">OpenMeru</a>. {{ __('allRightsReserved') }}</span>
    <span>{!! __('developedBy', ['name' => '<a href="https://www.linkedin.com/in/weslleyharakawa" target="_blank" rel="noopener">Weslley Harakawa</a>']) !!}</span>
    <span class="r">
      <a href="{{ $repo }}" target="_blank" rel="noopener">GitHub</a>
      <a href="/mail">{{ __('landing.footerWebmail') }}</a>
      <a href="{{ route('privacy') }}">{{ __('landing.footerPrivacy') }}</a>
      <a href="{{ route('terms') }}">{{ __('landing.footerTerms') }}</a>
    </span>
  </div>
</footer>

<script>
  (function () {
    var pick = document.getElementById("langPick"), btn = pick.querySelector(".lang-btn"), menu = pick.querySelector(".lang-menu");
    function toggle(open) { menu.classList.toggle("show", open); btn.setAttribute("aria-expanded", open ? "true" : "false"); }
    btn.addEventListener("click", function (e) { e.stopPropagation(); toggle(!menu.classList.contains("show")); });
    document.addEventListener("click", function (e) { if (!pick.contains(e.target)) toggle(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") toggle(false); });
  })();
  document.querySelectorAll(".tabs button").forEach(function (b) {
    b.addEventListener("click", function () {
      document.querySelectorAll(".tabs button").forEach(function (x) { x.setAttribute("aria-selected", x === b ? "true" : "false"); });
      document.getElementById("cmd").textContent = b.dataset.cmd;
    });
  });
  var copied = @json(__('landing.copied')), copy = @json(__('landing.copy'));
  document.querySelectorAll(".copy").forEach(function (b) {
    b.addEventListener("click", function () {
      var el = b.dataset.copy ? document.querySelector(b.dataset.copy) : b.parentElement.querySelector("pre");
      var text = el.textContent.replace(/\s+#.*$/gm, "");
      navigator.clipboard.writeText(text).then(function () { b.textContent = copied; setTimeout(function () { b.textContent = copy; }, 1500); });
    });
  });
</script>
</body>
</html>
