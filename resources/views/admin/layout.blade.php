<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" data-theme="light">
<head>
  @include('partials.head')
  <title>@yield('title') · Admin · {{ \App\Support\Brand::name() }}</title>
  <style>
    body { background: var(--bg); color: var(--ink); min-height: 100vh; }
    .admin-top { display:flex; align-items:center; gap:16px; padding:14px 24px; background:var(--surface); border-bottom:1px solid var(--line); flex-wrap:wrap; }
    .admin-top .brand-mini { display:flex; align-items:center; gap:10px; font-weight:700; font-size:18px; color:var(--ink); text-decoration:none; }
    .admin-top .brand-mini .brand-text { display:flex; flex-direction:column; line-height:1.1; }
    .admin-top .brand-mini .ver { font-weight:500; font-size:11px; color:var(--muted); letter-spacing:.02em; margin-top:3px; }
    .admin-top nav { display:flex; gap:6px; flex-wrap:wrap; }
    .admin-top nav a { padding:8px 14px; border-radius:10px; color:var(--muted); text-decoration:none; font-weight:500; }
    .admin-top nav a.active, .admin-top nav a:hover { background:var(--brand-soft); color:var(--brand); }
    .admin-top .spacer { flex:1; }
    .admin-main { max-width:1100px; margin:0 auto; padding:28px 16px 60px; }
    .app-footer { display:flex; flex-wrap:wrap; justify-content:center; gap:8px 18px; padding:18px 16px 28px; color:var(--muted); font-size:13px; }
    .app-footer a { color:var(--muted); }
    .panel { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); padding:22px; margin-bottom:22px; }
    .panel h2 { font-size:18px; font-weight:600; margin:0 0 16px; }
    .panel .hint { color:var(--muted); font-size:13px; }
    .form-control, .form-select { background:var(--surface); color:var(--ink); border-color:var(--line); border-radius:10px; }
    .form-label { font-size:13px; color:var(--muted); font-weight:500; }
    .btn-primary { background:var(--brand); border-color:var(--brand); border-radius:10px; }
    .btn-primary:hover { background:var(--brand-dark); border-color:var(--brand-dark); }
    .table { --bs-table-bg: transparent; color:var(--ink); }
    .table td, .table th { border-color:var(--line); vertical-align:middle; }
    .pill { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .pill.on { background:#dcfce7; color:#166534; } .pill.off { background:#fee2e2; color:#991b1b; } .pill.admin { background:var(--brand-soft); color:var(--brand); }
    code.copy { background:var(--bg); padding:3px 8px; border-radius:6px; word-break:break-all; }
    .domain-addon { background:var(--bg); color:var(--muted); border-color:var(--line); }
    .settings-layout { display:grid; grid-template-columns:230px 1fr; gap:22px; align-items:start; }
    .settings-nav { position:sticky; top:16px; display:flex; flex-direction:column; gap:4px; background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:10px; }
    .settings-nav a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:var(--muted); text-decoration:none; font-weight:500; }
    .settings-nav a.active, .settings-nav a:hover { background:var(--brand-soft); color:var(--brand); }
    .settings-panes .panel[hidden] { display:none; }
    .settings-panes .logo-mark { width:44px; height:44px; border-radius:12px; object-fit:contain; }
    .file-pick { display:flex; align-items:center; gap:10px; cursor:pointer; margin:0; }
    .file-pick .btn { border-radius:10px; }
    @media (max-width: 820px) { .settings-layout { grid-template-columns:1fr; } .settings-nav { position:static; flex-direction:row; flex-wrap:wrap; } }
  </style>
</head>
<body data-page="admin">
  @include('partials.demo-bar')
  <header class="admin-top">
    <a class="brand-mini" href="/admin">
      @include('partials.logo', ['class' => 'sm'])
      <span class="brand-text"><span>{{ \App\Support\Brand::isCustom() ? \App\Support\Brand::name() : 'OpenMeru' }} Admin</span><span class="ver" title="OpenMeru Mail {{ \App\Support\Version::current() }}">v{{ \App\Support\Version::current() }}</span></span>
    </a>
    <nav>
      <a href="{{ route('admin.accounts') }}" class="{{ request()->routeIs('admin.accounts') ? 'active' : '' }}"><i class="bi bi-people"></i> {{ __('admin.accounts') }}</a>
      <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}"><i class="bi bi-gear"></i> {{ __('admin.smtp') }}</a>
      <a href="{{ route('login') }}"><i class="bi bi-envelope"></i> {{ __('admin.myMailbox') }}</a>
    </nav>
    <span class="spacer"></span>
    <span class="hint" style="color:var(--muted)">{{ auth()->user()->email }}</span>
    @include('partials.lang-menu')
    <button class="icon-btn" type="button" data-action="theme" aria-label="{{ __('admin.theme') }}"><i class="bi bi-moon"></i></button>
    <form method="POST" action="/logout">@csrf<input type="hidden" name="next" value="/admin"><button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('signOut') }}</button></form>
  </header>
  <main class="admin-main">
    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @unless (auth()->user()->is_demo)
      @include('admin.update-banner')
    @endunless
    @yield('content')
  </main>
  @include('partials.footer')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="/assets/js/mail.js?v={{ filemtime(public_path('assets/js/mail.js')) }}"></script>
  <script>
    (function () {
      document.querySelectorAll("[data-reveal]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var input = document.getElementById(btn.getAttribute("data-reveal"));
          var show = input.type === "password";
          input.type = show ? "text" : "password";
          btn.querySelector("i").className = show ? "bi bi-eye-slash" : "bi bi-eye";
        });
      });
    })();
  </script>
</body>
</html>
