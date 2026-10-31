@extends('auth.layout')

@section('page', 'signup')

@section('card')
  @include('partials.logo', ['class' => ''])
  <h1 class="auth-title" data-i18n="signupTitle">{{ __('signupTitle') }}</h1>
  <p class="auth-lead" data-i18n="signupLead">{{ __('signupLead', ['domain' => config('inbound.domain')]) }}</p>
  <form id="signupForm" class="stack" novalidate>
    <label class="field">
      <i class="bi bi-person"></i>
      <input name="name" autocomplete="name" data-i18n-ph="name" placeholder="{{ __('name') }}" required maxlength="120">
    </label>
    <label class="field field-domain">
      <i class="bi bi-at"></i>
      <input name="local" autocomplete="username" data-i18n-ph="signupLocalPh" placeholder="{{ __('signupLocalPh') }}" required maxlength="64" pattern="[a-z0-9]([a-z0-9._-]*[a-z0-9])?" style="text-transform:lowercase">
      <span class="domain-suffix">@{{ config('inbound.domain') }}</span>
    </label>
    <label class="field">
      <i class="bi bi-lock"></i>
      <input name="password" type="password" autocomplete="new-password" minlength="8" data-i18n-ph="password" placeholder="{{ __('password') }}" required>
      <button class="eye" type="button" data-toggle-eye data-i18n-aria="showPw" aria-label="{{ __('showPw') }}"><i class="bi bi-eye"></i></button>
    </label>
    <label class="field">
      <i class="bi bi-lock-fill"></i>
      <input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" data-i18n-ph="confirmPassword" placeholder="{{ __('confirmPassword') }}" required>
      <button class="eye" type="button" data-toggle-eye data-i18n-aria="showPw" aria-label="{{ __('showPw') }}"><i class="bi bi-eye"></i></button>
    </label>
    <label class="field">
      <i class="bi bi-life-preserver"></i>
      <input name="recovery_email" type="email" autocomplete="email" data-i18n-ph="recoveryEmailOptional" placeholder="{{ __('recoveryEmailOptional') }}">
    </label>
    <p id="formError" class="form-error" role="alert"></p>
    <button class="btn-go" type="submit"><span data-i18n="signupButton">{{ __('signupButton') }}</span> <i class="bi bi-arrow-right"></i></button>
    <a class="forgot" href="{{ route('login') }}" style="justify-self:center;margin-top:4px" data-i18n="backLogin">{{ __('backLogin') }}</a>
  </form>
@endsection
