@extends('auth.layout')

@section('page', 'forgot')

@section('card')
  <div id="resetForm">
    <svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect width="64" height="64" rx="18" fill="url(#logoR)"/>
      <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
      <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
    </svg>
    <h1 class="auth-title" data-i18n="forgotTitle">{{ __('forgotTitle') }}</h1>
    <p class="auth-lead" data-i18n="forgotLead">{{ __('forgotLead') }}</p>
    <form id="forgotForm" class="stack" novalidate>
      <label class="field">
        <i class="bi bi-envelope"></i>
        <input name="email" type="email" autocomplete="username" data-i18n-ph="email" placeholder="{{ __('email') }}" required>
      </label>
      <p id="formError" class="form-error" role="alert"></p>
      <button class="btn-go" type="submit"><span data-i18n="sendLink">{{ __('sendLink') }}</span> <i class="bi bi-arrow-right"></i></button>
      <a class="forgot" href="{{ route('login') }}" style="justify-self:center;margin-top:4px" data-i18n="backLogin">{{ __('backLogin') }}</a>
    </form>
  </div>
  <div id="resetDone" class="done" hidden>
    <div class="done-icon"><i class="bi bi-envelope-check"></i></div>
    <h2 data-i18n="sentTitle">{{ __('sentTitle') }}</h2>
    <p data-i18n="sentLead">{{ __('sentLead') }}</p>
    <a href="{{ route('login') }}" data-i18n="backLogin">{{ __('backLogin') }}</a>
  </div>
@endsection
