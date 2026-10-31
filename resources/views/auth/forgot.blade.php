@extends('auth.layout')

@section('page', 'forgot')

@section('card')
  <div id="resetForm">
    @include('partials.logo', ['class' => ''])
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
