<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" data-theme="light">
<head>
  @include('partials.head')
  <meta name="theme-color" content="#f3f5f8">
  <title>{{ __('accountSettings') }} · OpenMeru Mail</title>
</head>
<body data-page="settings" data-email="{{ $user->email }}" data-name="{{ $user->name }}">
  @include('partials.demo-bar')
  @include('partials.impersonating')
  @include('partials.mailbox-bar', ['user' => $user])
  <div class="shell settings-shell">
    @include('partials.sidebar', ['user' => $user, 'withFolders' => false])
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" data-action="menu" data-i18n-aria="menu" aria-label="{{ __('menu') }}"><i class="bi bi-list"></i></button>
      <h1 class="settings-title" data-i18n="accountSettings">{{ __('accountSettings') }}</h1>
      @include('partials.top-actions', ['user' => $user])
    </header>
    <main class="settings-main">
      <div class="settings-card">
        <div class="settings-tabs" role="tablist">
          <a class="settings-tab" role="tab" href="#profile" data-tab="profile"><i class="bi bi-person"></i> <span data-i18n="profile">{{ __('profile') }}</span></a>
          <a class="settings-tab" role="tab" href="#password" data-tab="password"><i class="bi bi-key"></i> <span data-i18n="password">{{ __('password') }}</span></a>
          <a class="settings-tab" role="tab" href="#forwarding" data-tab="forwarding"><i class="bi bi-forward"></i> <span data-i18n="forwarding">{{ __('forwarding') }}</span></a>
          <a class="settings-tab" role="tab" href="#notify" data-tab="notify"><i class="bi bi-bell"></i> <span data-i18n="notifications">{{ __('notifications') }}</span></a>
          <a class="settings-tab danger" role="tab" href="#delete" data-tab="delete"><i class="bi bi-trash"></i> <span data-i18n="deleteAccount">{{ __('deleteAccount') }}</span></a>
        </div>

        <section class="settings-pane" data-pane="profile">
          <div class="avatar-edit">
            <div class="avatar-big" id="avatarBig">@include('partials.avatar', ['user' => $user])</div>
            <div>
              <label class="btn btn-outline-primary btn-sm mb-0" style="cursor:pointer">
                <i class="bi bi-camera"></i> <span data-i18n="uploadPhoto">{{ __('uploadPhoto') }}</span>
                <input id="avatarInput" type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
              </label>
              <button class="btn btn-outline-secondary btn-sm" type="button" id="avatarRemove" @if (! $user->avatar_path) hidden @endif data-i18n="removePhoto">{{ __('removePhoto') }}</button>
              <div class="form-text" data-i18n="photoHint">{{ __('photoHint') }}</div>
            </div>
          </div>
          <form id="profileForm">
            <label class="form-label" data-i18n="name">{{ __('name') }}</label>
            <input class="form-control mb-3" name="name" required maxlength="120" value="{{ $user->name }}">
            <label class="form-label" data-i18n="recoveryEmail">{{ __('recoveryEmail') }}</label>
            <input class="form-control mb-1" name="recovery_email" type="email" value="{{ $user->recovery_email }}">
            <div class="form-text mb-3" data-i18n="recoveryHint">{{ __('recoveryHint') }}</div>
            <label class="form-label" data-i18n="signature">{{ __('signature') }}</label>
            <textarea class="form-control mb-3" name="signature" rows="4" maxlength="2000">{{ $user->signature }}</textarea>
            <button class="btn btn-primary" type="submit" data-i18n="save">{{ __('save') }}</button>
          </form>
        </section>

        <section class="settings-pane" data-pane="password" hidden>
          <form id="passwordForm">
            <label class="form-label" data-i18n="currentPassword">{{ __('currentPassword') }}</label>
            <input class="form-control mb-3" name="current_password" type="password" autocomplete="current-password" required>
            <label class="form-label" data-i18n="newPassword">{{ __('newPassword') }}</label>
            <input class="form-control mb-3" name="password" type="password" autocomplete="new-password" minlength="8" required>
            <label class="form-label" data-i18n="confirmPassword">{{ __('confirmPassword') }}</label>
            <input class="form-control mb-3" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            <button class="btn btn-primary" type="submit" data-i18n="savePassword">{{ __('savePassword') }}</button>
          </form>
        </section>

        <section class="settings-pane" data-pane="forwarding" hidden>
          <form id="forwardingForm">
            <p class="form-text" data-i18n="forwardLead">{{ __('forwardLead') }}</p>
            <label class="form-label" data-i18n="forwardTo">{{ __('forwardTo') }}</label>
            <input class="form-control mb-3" name="forward_to" type="email" placeholder="you@gmail.com" value="{{ $user->forward_to }}">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="forward_keep" id="forwardKeep" @checked($user->forward_keep !== false)>
              <label class="form-check-label" for="forwardKeep" data-i18n="forwardKeep">{{ __('forwardKeep') }}</label>
            </div>
            <button class="btn btn-primary" type="submit" data-i18n="save">{{ __('save') }}</button>
          </form>
        </section>

        <section class="settings-pane" data-pane="notify" hidden>
          <p class="form-text" data-i18n="notifyLead">{{ __('notifyLead') }}</p>
          <div id="notifyState" class="mb-3"></div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="button" id="pushOn" data-i18n="enablePush">{{ __('enablePush') }}</button>
            <button class="btn btn-outline-secondary" type="button" id="pushTest" data-i18n="testPush">{{ __('testPush') }}</button>
            <button class="btn btn-outline-danger" type="button" id="pushOff" data-i18n="disablePush">{{ __('disablePush') }}</button>
          </div>
        </section>

        <section class="settings-pane" data-pane="delete" hidden>
          @if ($user->is_admin)
            <p class="form-text" data-i18n="adminCannotDelete">{{ __('adminCannotDelete') }}</p>
          @else
            <div class="alert alert-danger" data-i18n="deleteWarning">{{ __('deleteWarning') }}</div>
            <form id="deleteForm">
              <label class="form-label" data-i18n="confirmWithPassword">{{ __('confirmWithPassword') }}</label>
              <input class="form-control mb-3" name="password" type="password" autocomplete="current-password" required>
              <button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i> <span data-i18n="deleteForeverAccount">{{ __('deleteForeverAccount') }}</span></button>
            </form>
          @endif
        </section>
        <p class="form-error mt-3 mb-0" id="settingsError" role="alert"></p>
      </div>
    </main>
    @include('partials.footer')
    <div class="backdrop" id="backdrop"></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/mail.js?v={{ filemtime(public_path('assets/js/mail.js')) }}"></script>
</body>
</html>
