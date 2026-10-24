@extends('auth.layout')

@section('page', 'reset')

@section('card')
  <svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true">
    <rect width="64" height="64" rx="18" fill="url(#logoR)"/>
    <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
    <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
  </svg>
  <h1 class="auth-title" data-i18n="newPwTitle">{{ __('newPwTitle') }}</h1>
  <form id="resetPwForm" class="stack" novalidate>
    <input type="hidden" name="token" value="{{ $token }}">
    <label class="field">
      <i class="bi bi-envelope"></i>
      <input name="email" type="email" autocomplete="username" value="{{ $email }}" data-i18n-ph="email" placeholder="{{ __('email') }}" required>
    </label>
    <label class="field">
      <i class="bi bi-lock"></i>
      <input name="password" type="password" autocomplete="new-password" data-i18n-ph="newPassword" placeholder="{{ __('newPassword') }}" required>
    </label>
    <label class="field">
      <i class="bi bi-lock"></i>
      <input name="password_confirmation" type="password" autocomplete="new-password" data-i18n-ph="confirmPassword" placeholder="{{ __('confirmPassword') }}" required>
    </label>
    <p id="formError" class="form-error" role="alert"></p>
    <button class="btn-go" type="submit"><span data-i18n="savePassword">{{ __('savePassword') }}</span> <i class="bi bi-arrow-right"></i></button>
  </form>
@endsection
