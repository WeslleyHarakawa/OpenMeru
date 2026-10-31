{{-- The product mark: the uploaded icon (small sizes) or logo when there is one, else OpenMeru's own mark (Fuji in an envelope). --}}
@php($class = $class ?? '')
{{-- 'product' forces the OpenMeru mark whatever the client's branding (the admin header is always OpenMeru). --}}
@php($product = $product ?? false)
@php($url = $product ? null : (str_contains($class, 'sm') ? \App\Support\Brand::iconUrl() : (\App\Support\Brand::logoUrl() ?: \App\Support\Brand::iconUrl())))
@if ($url)
  <img class="logo-mark {{ $class }}" src="{{ $url }}" alt="{{ \App\Support\Brand::name() }}">
@else
  <img class="logo-mark product {{ $class }}" src="/assets/brand/mark.png" alt="OpenMeru">
@endif
