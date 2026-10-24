@extends('auth.layout')

@section('page', 'login')

@section('card')
  <svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true">
    <rect width="64" height="64" rx="18" fill="url(#logoR)"/>
    <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
    <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
  </svg>
  <h1 class="wordmark">OpenMeru</h1>
  <p class="product">{{ !empty($admin) ? 'ADMIN' : 'MAIL' }}</p>
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
