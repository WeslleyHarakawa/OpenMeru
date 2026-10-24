{{-- Language picker: globe button and a two-column list of the ten languages. --}}
<div class="dropdown">
  <button class="icon-btn lang-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('language') }}">
    <i class="bi bi-globe2"></i><span class="lang-code">{{ strtoupper(str_replace('_', '-', app()->getLocale())) }}</span>
  </button>
  <div class="dropdown-menu dropdown-menu-end lang-menu">
    @foreach (\App\Support\Locales::ALL as $code => [$native])
      <button class="dropdown-item @if (app()->getLocale() === $code) active @endif" type="button" data-lang="{{ $code }}" lang="{{ \App\Support\Locales::tag($code) }}">{{ $native }}</button>
    @endforeach
  </div>
</div>
