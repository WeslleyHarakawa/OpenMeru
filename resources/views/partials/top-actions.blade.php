{{-- Right side of the top bar: language, theme, notifications, account menu. --}}
<div class="top-actions">
  @include('partials.lang-menu')
  <button class="icon-btn" type="button" data-action="theme" aria-label="{{ __('admin.theme') }}"><i class="bi bi-moon"></i></button>
  <div class="dropdown">
    <button class="icon-btn bell-btn" id="bellBtn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-i18n-aria="notifications" aria-label="{{ __('notifications') }}" style="position:relative">
      <i class="bi bi-bell"></i>
      <span class="bell-badge" id="bellBadge" hidden></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end bell-menu" id="bellMenu">
      <div class="bell-head"><strong data-i18n="notifications">{{ __('notifications') }}</strong></div>
      <div id="bellList"></div>
      <div class="bell-foot" id="bellPush"></div>
    </div>
  </div>
  <div class="dropdown">
    <button class="avatar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('admin.colAccount') }}">
      @include('partials.avatar', ['user' => $user])
    </button>
    <div class="dropdown-menu dropdown-menu-end">
      <div class="who"><strong id="whoName">{{ $user->name }}</strong><span id="accountEmail">{{ $user->email }}</span></div>
      <a class="menu-signout" href="/settings#profile"><i class="bi bi-person"></i> <span data-i18n="editProfile">{{ __('editProfile') }}</span></a>
      <a class="menu-signout" href="/settings#password"><i class="bi bi-key"></i> <span data-i18n="changePassword">{{ __('changePassword') }}</span></a>
      <a class="menu-signout" href="/settings#forwarding"><i class="bi bi-forward"></i> <span data-i18n="forwarding">{{ __('forwarding') }}</span></a>
      <a class="menu-signout" href="/settings#notify"><i class="bi bi-bell"></i> <span data-i18n="notifications">{{ __('notifications') }}</span></a>
      @if ($user->is_admin)
        <a class="menu-signout" href="/admin"><i class="bi bi-shield-lock"></i> Admin</a>
      @endif
      <form method="POST" action="/logout">
        @csrf
        <button class="menu-signout" type="submit"><i class="bi bi-box-arrow-right"></i> <span data-i18n="signOut">{{ __('signOut') }}</span></button>
      </form>
    </div>
  </div>
</div>
