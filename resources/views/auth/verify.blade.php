@extends('auth.layout')

@section('page', 'verify')

@section('card')
  @include('partials.logo', ['class' => ''])
  <h1 class="auth-title">{{ __('admin.codeTitle') }}</h1>
  <p class="auth-lead">{{ __('admin.codeLead', ['dest' => $destination]) }}</p>
  <form method="POST" action="{{ route('admin.verify') }}" class="stack" autocomplete="off">
    @csrf
    <label class="field">
      <i class="bi bi-shield-lock"></i>
      <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" required autofocus style="letter-spacing:6px;font-size:20px">
    </label>
    <p class="form-error" role="alert">{{ $sendError ?? session('otp_error') }}</p>
    @if (session('otp_status'))
      <p style="margin:0;color:var(--brand);font-size:14px">{{ session('otp_status') }}</p>
    @endif
    <button class="btn-go" type="submit"><span>{{ __('admin.openAdmin') }}</span> <i class="bi bi-arrow-right"></i></button>
  </form>
  <div style="display:flex;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap">
    <form method="POST" action="{{ route('admin.verify.resend') }}">
      @csrf
      <button type="submit" class="forgot" style="background:none;border:0;padding:0;cursor:pointer">{{ __('admin.resendCode') }}</button>
    </form>
    <form method="POST" action="/logout">
      @csrf
      <input type="hidden" name="next" value="/admin">
      <button type="submit" class="forgot" style="background:none;border:0;padding:0;cursor:pointer">{{ __('signOut') }}</button>
    </form>
  </div>
@endsection
