@php($locale = app()->getLocale())
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag($locale) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('landing.footerDocs') }} — OpenMeru</title>
  <meta name="description" content="{{ __('landing.metaDescription') }}">
  <link rel="canonical" href="{{ url('/docs') }}">
  <link rel="icon" href="/assets/brand/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous" rel="stylesheet">
  <style>
    :root { --bg: #fdfcfc; --bg2: #f5f3f2; --ink: #201d1d; --muted: #6b6566; --faint: #9a9394; --line: #e6e2e1; --code: #f1eeed; --accent: #d2151a; }
    @media (prefers-color-scheme: dark) { :root { --bg: #0d0c0c; --bg2: #151313; --ink: #eeeaea; --muted: #a29b9c; --faint: #6f6869; --line: #272323; --code: #181616; --accent: #ff5a5f; } }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.65 Inter, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    a { color: var(--accent); }
    header.top { position: sticky; top: 0; z-index: 10; background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
    header.top .wrap { max-width: 1180px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; height: 60px; gap: 12px; }
    .brand img { height: 40px; width: auto; display: block; }
    .brand .dark { display: none; }
    @media (prefers-color-scheme: dark) { .brand .light { display: none; } .brand .dark { display: block; } }
    nav.links { display: flex; gap: 18px; font-size: 14px; align-items: center; font-family: "IBM Plex Mono", monospace; }
    nav.links a { text-decoration: none; color: var(--muted); display: inline-flex; align-items: center; gap: 6px; }
    nav.links a:hover { color: var(--ink); }
    nav.links a.btn { border: 1px solid var(--accent); color: var(--accent); border-radius: 8px; padding: 7px 14px; font-weight: 600; }
    .layout { max-width: 1180px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 40px; }
    aside { position: sticky; top: 60px; align-self: start; max-height: calc(100vh - 60px); overflow: auto; padding: 28px 0; border-right: 1px solid var(--line); }
    aside h4 { font: 600 11px/1 "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; color: var(--faint); margin: 0 0 10px; }
    aside a { display: block; color: var(--muted); text-decoration: none; font-size: 14px; padding: 5px 12px 5px 0; border-radius: 6px; }
    aside a:hover, aside a.on { color: var(--accent); }
    main { padding: 28px 0 80px; min-width: 0; }
    main h1 { font-family: "IBM Plex Mono", monospace; font-size: 34px; letter-spacing: -.03em; margin: 0 0 8px; }
    main h2 { font-family: "IBM Plex Mono", monospace; font-size: 22px; letter-spacing: -.02em; margin: 44px 0 12px; padding-top: 12px; border-top: 1px solid var(--line); }
    main h3 { font-size: 17px; margin: 26px 0 8px; }
    main p, main li { color: var(--muted); }
    main p strong, main li strong { color: var(--ink); }
    main code { font-family: "IBM Plex Mono", monospace; font-size: 13px; background: var(--code); border: 1px solid var(--line); border-radius: 4px; padding: 1px 5px; color: var(--ink); }
    main pre { background: var(--code); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; overflow-x: auto; font-size: 13px; line-height: 1.6; }
    main pre code { background: none; border: 0; padding: 0; }
    main table { border-collapse: collapse; width: 100%; font-size: 14px; margin: 12px 0; }
    main th, main td { border: 1px solid var(--line); padding: 8px 10px; text-align: left; vertical-align: top; }
    main img { max-width: 100%; border-radius: 8px; }
    main hr { border: 0; border-top: 1px solid var(--line); margin: 30px 0; }
    footer { border-top: 1px solid var(--line); font: 12px "IBM Plex Mono", monospace; color: var(--faint); }
    footer .wrap { max-width: 1180px; margin: 0 auto; padding: 22px 20px 40px; display: flex; flex-wrap: wrap; gap: 8px 22px; justify-content: space-between; }
    footer a { color: inherit; text-decoration: underline; }
    @media (max-width: 860px) {
      .layout { grid-template-columns: minmax(0, 1fr); gap: 0; }
      aside { position: static; max-height: none; border-right: 0; border-bottom: 1px solid var(--line); padding: 18px 0; }
      aside a { display: inline-block; padding: 4px 10px 4px 0; }
      nav.links a.hide-sm { display: none; }
    }
  </style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="brand" href="/">
      <img class="light" src="/assets/brand/logo-light.png" alt="OpenMeru" width="1086" height="290">
      <img class="dark" src="/assets/brand/logo-dark.png" alt="OpenMeru" width="650" height="174">
    </a>
    <nav class="links">
      <a class="hide-sm" href="/">{{ __('landing.navHome') }}</a>
      <a href="{{ $repo }}" target="_blank" rel="noopener"><i class="bi bi-github"></i> GitHub</a>
      <a class="btn" href="https://buymeacoffee.com/weslleyharakawa" target="_blank" rel="noopener"><i class="bi bi-heart"></i> {{ __('landing.footerCoffee') }}</a>
    </nav>
  </div>
</header>

<div class="layout">
  <aside>
    <h4>{{ __('landing.footerDocs') }}</h4>
    @foreach ($sections as $s)
      <a href="#{{ $s['id'] }}">{{ $s['title'] }}</a>
    @endforeach
  </aside>
  <main>{!! $html !!}</main>
</div>

<footer>
  <div class="wrap">
    <span><a href="/">← openmeru.com</a></span>
    <span>© {{ date('Y') }} <a href="https://github.com/WeslleyHarakawa" target="_blank" rel="noopener">Weslley Harakawa</a> · <a href="{{ route('privacy') }}">{{ __('landing.footerPrivacy') }}</a> · <a href="{{ route('terms') }}">{{ __('landing.footerTerms') }}</a> · <a href="{{ $repo }}/blob/main/LICENSE" target="_blank" rel="noopener">{{ __('landing.footerLicense') }}</a></span>
  </div>
</footer>
<script>
  var links = [].slice.call(document.querySelectorAll("aside a")), heads = [].slice.call(document.querySelectorAll("main h2[id]"));
  function mark() { var y = window.scrollY + 90, cur = heads[0]; heads.forEach(function (h) { if (h.offsetTop <= y) cur = h; }); links.forEach(function (a) { a.classList.toggle("on", cur && a.getAttribute("href") === "#" + cur.id); }); }
  window.addEventListener("scroll", mark); mark();
</script>
</body>
</html>
