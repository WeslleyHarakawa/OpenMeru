@extends('auth.layout')

@section('page', 'login')

@section('card')
  @include('partials.logo', ['class' => ''])
  <h1 class="wordmark">{{ \App\Support\Brand::wordmark() }}</h1>
  <p class="product">{{ \App\Support\Brand::product(!empty($admin) ? 'ADMIN' : 'MAIL') }}</p>
  @if (!empty($notAdmin))
    <p class="form-error" style="text-align:center;margin-top:12px">{{ __('admin.notAdmin', ['email' => $notAdmin]) }}</p>
  @endif
  <form id="loginForm" class="stack" novalidate>
    @if (!empty($next))
      <input type="hidden" name="next" value="{{ $next }}">
    @endif
    <label class="field">
      <i class="bi bi-envelope"></i>
      <input name="email" type="email" autocomplete="username" data-i18n-ph="email" placeholder="{{ __('email') }}">
    </label>
    <label class="field">
      <i class="bi bi-lock"></i>
      <input name="password" type="password" autocomplete="current-password" data-i18n-ph="password" placeholder="{{ __('password') }}">
      <button class="eye" type="button" data-action="toggle-password" data-i18n-aria="showPw" aria-label="{{ __('showPw') }}"><i class="bi bi-eye"></i></button>
    </label>
    <a class="forgot" href="/forgot" data-i18n="forgot">{{ __('forgot') }}</a>
    <p id="formError" class="form-error" role="alert"></p>
    <button class="btn-go" type="submit"><span data-i18n="signIn">{{ __('signIn') }}</span> <i class="bi bi-arrow-right"></i></button>
  </form>
@endsection
