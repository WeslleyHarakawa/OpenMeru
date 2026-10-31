{{-- The product mark: the uploaded icon (small sizes) or logo when there is one, else the OpenMeru envelope. --}}
@php($class = $class ?? '')
{{-- 'product' forces the OpenMeru mark whatever the client's branding (the admin header is always OpenMeru). --}}
@php($product = $product ?? false)
@php($gid = 'logo'.substr(md5($class.uniqid('', true)), 0, 6))
@php($url = $product ? null : (str_contains($class, 'sm') ? \App\Support\Brand::iconUrl() : (\App\Support\Brand::logoUrl() ?: \App\Support\Brand::iconUrl())))
@if ($url)
  <img class="logo-mark {{ $class }}" src="{{ $url }}" alt="{{ \App\Support\Brand::name() }}">
@else
  <svg class="logo-mark {{ $class }}" viewBox="0 0 64 64" aria-hidden="true">
    <defs><linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6ea8ff"/><stop offset="100%" stop-color="#1d57f5"/></linearGradient></defs>
    <rect width="64" height="64" rx="18" fill="url(#{{ $gid }})"/>
    <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
    <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
  </svg>
@endif
