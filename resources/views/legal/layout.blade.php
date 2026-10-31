<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" data-theme="light">
<head>
  @include('partials.head')
  <title>@yield('title') · OpenMeru Mail</title>
</head>
<body data-page="legal">
  <div class="legal-wrap">
    <header class="legal-head">
      <a class="side-brand" href="/" style="padding:0">
        <svg class="logo-mark sm" viewBox="0 0 64 64" aria-hidden="true">
          <defs><linearGradient id="logoG" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6ea8ff"/><stop offset="100%" stop-color="#1d57f5"/></linearGradient></defs>
          <rect width="64" height="64" rx="18" fill="url(#logoG)"/>
          <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
          <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
        </svg>
        <span><strong>OpenMeru</strong><span>MAIL</span></span>
      </a>
      <div class="d-flex align-items-center gap-1">
        @include('partials.lang-menu')
        <button class="icon-btn" type="button" data-action="theme" aria-label="{{ __('admin.theme') }}"><i class="bi bi-moon"></i></button>
      </div>
    </header>
    <article class="legal-body">
      <h1>@yield('title')</h1>
      <p class="legal-updated">{{ __('legal.updated', ['date' => \Illuminate\Support\Carbon::create(2026, 10, 20)->locale(app()->getLocale())->isoFormat('LL')]) }}</p>
      @yield('content')
    </article>
    @include('partials.footer')
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="/assets/js/mail.js?v={{ filemtime(public_path('assets/js/mail.js')) }}"></script>
</body>
</html>
