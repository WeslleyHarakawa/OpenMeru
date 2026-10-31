{{-- Always-visible strip naming the mailbox open on screen; admins get a way back to /admin. --}}
<div class="mailbox-bar" role="status">
  <i class="bi bi-envelope-open"></i>
  <span data-i18n="openMailbox">{{ __('openMailbox') }}</span>
  <strong>{{ $user->email }}</strong>
  @if ($user->is_admin && ! session('impersonator'))
    <a class="mailbox-bar-admin" href="/admin"><i class="bi bi-shield-lock"></i> {{ __('admin.backToAdmin') }}</a>
  @endif
</div>
