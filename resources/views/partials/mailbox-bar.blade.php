{{-- Always-visible strip naming the mailbox open on screen. --}}
<div class="mailbox-bar" role="status">
  <i class="bi bi-envelope-open"></i>
  <span data-i18n="openMailbox">{{ __('openMailbox') }}</span>
  <strong>{{ $user->email }}</strong>
</div>
