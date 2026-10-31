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
        <img class="logo-mark sm" src="/assets/brand/mark.png" alt="OpenMeru">
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
