<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous" rel="stylesheet">
<link href="/assets/css/mail.css?v={{ filemtime(public_path('assets/css/mail.css')) }}" rel="stylesheet">
<script>
  window.LANG = @json(app()->getLocale());
  window.LANG_TAG = @json(\App\Support\Locales::tag(app()->getLocale()));
  window.MAIL_DOMAIN = @json(config('inbound.domain'));
  window.I18N = @json(\App\Support\Locales::dictionary(app()->getLocale()));
  (function () {
    var theme = "light";
    try { theme = localStorage.getItem("openmeru-theme") || "light"; } catch (e) {}
    document.documentElement.setAttribute("data-theme", theme);
    document.documentElement.setAttribute("data-bs-theme", theme);
  })();
</script>
