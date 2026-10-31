<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" data-theme="light">
<head>
  @include('partials.head')
  <meta name="theme-color" content="#2f6bff">
  <title>{{ \App\Support\Brand::name() }}</title>
</head>
<body data-page="@yield('page')">
  <div class="auth">
    <section class="brand" aria-hidden="true">
      <svg class="brand-waves" viewBox="0 0 800 1200" preserveAspectRatio="xMidYMid slice">
        <path fill="rgba(255,255,255,.12)" d="M0 220C160 80 280 340 520 180C680 80 740 240 800 150V0H0Z"/>
        <path fill="rgba(255,255,255,.08)" d="M-80 700C80 540 220 860 460 700C680 560 760 820 900 680L900 980C640 820 420 1040 180 860C0 740-40 860-80 800Z"/>
        <path fill="rgba(8,24,90,.28)" d="M0 980C180 860 300 1140 560 980C720 860 760 1080 800 1000V1200H0Z"/>
      </svg>
      <div class="brand-lockup">
        @include('partials.logo', ['class' => 'lg'])
        <div class="wordmark">{{ \App\Support\Brand::wordmark() }}</div>
        @if (\App\Support\Brand::product())<div class="product">\App\Support\Brand::product()</div>@endif
      </div>
    </section>
    <main class="auth-panel">
      <div class="auth-tools">
        @include('partials.lang-menu')
        <button class="icon-btn" type="button" data-action="theme" aria-label="{{ __('admin.theme') }}"><i class="bi bi-moon"></i></button>
      </div>
      <div class="auth-card">
        <svg width="0" height="0" aria-hidden="true">
          <defs>
            <linearGradient id="logoR" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#6ea8ff"/>
              <stop offset="100%" stop-color="#1d57f5"/>
            </linearGradient>
          </defs>
        </svg>
        @yield('card')
      </div>
      @include('partials.footer')
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="/assets/js/mail.js?v={{ filemtime(public_path('assets/js/mail.js')) }}"></script>
</body>
</html>
