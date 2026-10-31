@extends('auth.layout')

@section('page', 'two-factor')

@section('card')
  @include('partials.logo', ['class' => ''])
  <h1 class="auth-title" data-i18n="twoFactorTitle">{{ __('twoFactorTitle') }}</h1>
  <p class="auth-lead" data-i18n="twoFactorLead">{{ __('twoFactorLead') }}</p>
  <form id="twoFactorForm" class="stack" novalidate>
    <label class="field">
      <i class="bi bi-shield-lock"></i>
      <input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus data-i18n-ph="twoFactorCodePh" placeholder="{{ __('twoFactorCodePh') }}" required>
    </label>
    <p id="formError" class="form-error" role="alert"></p>
    <button class="btn-go" type="submit"><span data-i18n="twoFactorVerify">{{ __('twoFactorVerify') }}</span> <i class="bi bi-arrow-right"></i></button>
    <p class="hint" style="text-align:center;margin:6px 0 0" data-i18n="twoFactorRecoveryHint">{{ __('twoFactorRecoveryHint') }}</p>
    <form method="POST" action="/logout" id="twoFactorLogout" style="display:contents">@csrf</form>
    <button class="forgot" type="submit" form="twoFactorLogout" style="justify-self:center;margin-top:4px;background:none;border:0" data-i18n="signOut">{{ __('signOut') }}</button>
  </form>
@endsection
