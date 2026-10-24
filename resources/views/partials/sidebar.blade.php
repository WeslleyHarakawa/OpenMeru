<aside class="sidebar">
  <a class="side-brand" href="/mail">
    <svg class="logo-mark sm" viewBox="0 0 64 64" aria-hidden="true">
      <defs>
        <linearGradient id="logoS" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#6ea8ff"/>
          <stop offset="100%" stop-color="#1d57f5"/>
        </linearGradient>
      </defs>
      <rect width="64" height="64" rx="18" fill="url(#logoS)"/>
      <path fill="#fff" d="M15 24h34a2 2 0 0 1 2 2v18a2 2 0 0 1-2 2H15a2 2 0 0 1-2-2V26a2 2 0 0 1 2-2z"/>
      <path d="M14 25.2 32 37.2 50 25.2" fill="none" stroke="#2f6bff" stroke-width="2.2" stroke-linejoin="round"/>
    </svg>
    <span><strong>OpenMeru</strong><span>MAIL</span></span>
  </a>
  <div class="side-account" title="{{ $user->email }}"><i class="bi bi-person-circle"></i> {{ $user->email }}</div>
  @if ($withFolders)
    <button class="compose" type="button" data-action="compose"><i class="bi bi-pencil-square"></i> <span data-i18n="compose">{{ __('compose') }}</span></button>
    <div class="nav-scroll" id="nav"></div>
  @else
    <a class="compose" href="/mail" style="text-decoration:none"><i class="bi bi-arrow-left"></i> <span data-i18n="backToInbox">{{ __('backToInbox') }}</span></a>
    <div class="nav-scroll"></div>
  @endif
  <div class="side-bottom">
    <a class="nav-item" href="/settings#profile"><i class="bi bi-gear"></i><span data-i18n="settings">{{ __('settings') }}</span></a>
    <form method="POST" action="/logout">
      @csrf
      <button class="nav-item" type="submit"><i class="bi bi-box-arrow-right"></i><span data-i18n="signOut">{{ __('signOut') }}</span></button>
    </form>
  </div>
</aside>
