<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" data-theme="light">
<head>
  @include('partials.head')
  <meta name="theme-color" content="#f3f5f8">
  <title>OpenMeru Mail</title>
  <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" integrity="sha384-ecIckRi4QlKYya/FQUbBUjS4qp65jF/J87Guw5uzTbO1C1Jfa/6kYmd6dXUF6D7i" crossorigin="anonymous" rel="stylesheet">
</head>
<body data-page="inbox" data-email="{{ $user->email }}" data-name="{{ $user->name }}">
  @include('partials.demo-bar')
  @include('partials.impersonating')
  @include('partials.mailbox-bar', ['user' => $user])
  <div class="shell">
    @include('partials.sidebar', ['user' => $user, 'withFolders' => true])
    <header class="topbar">
      <button class="icon-btn menu-btn" type="button" data-action="menu" data-i18n-aria="menu" aria-label="{{ __('menu') }}"><i class="bi bi-list"></i></button>
      <label class="search">
        <i class="bi bi-search"></i>
        <input id="search" type="search" data-i18n-ph="search" placeholder="{{ __('search') }}">
      </label>
      <div class="greeting" id="greeting"></div>
      @include('partials.top-actions', ['user' => $user])
    </header>
    <section class="list">
      <div class="list-tools">
        <button class="tool-btn" type="button" data-action="refresh" data-i18n-aria="refresh" aria-label="{{ __('refresh') }}"><i class="bi bi-arrow-clockwise"></i></button>
        <strong class="list-title" id="listTitle">{{ __('inbox') }}</strong>
        <span class="range" id="range"></span>
      </div>
      <div class="rows" id="rows"></div>
    </section>
    <section class="reader" id="reader"></section>
    <section class="compose-screen" id="composeScreen">
      <form class="compose-form" id="composeForm">
        <div class="compose-head">
          <h2 id="composeHeading">{{ __('newMessage') }}</h2>
          <button class="icon-btn" type="button" data-action="discard" data-i18n-aria="close" aria-label="{{ __('close') }}"><i class="bi bi-x-lg"></i></button>
        </div>
        <label class="compose-line"><span data-i18n="from">{{ __('from') }}</span><input id="composeFrom" type="email" readonly value="{{ $user->email }}"></label>
        <label class="compose-line compose-to"><span data-i18n="to">{{ __('to') }}</span><input id="composeTo" type="text" autocomplete="off" data-i18n-ph="to" placeholder="{{ __('to') }}"><span class="cc-toggles"><button type="button" class="cc-toggle" data-action="show-cc" data-i18n="cc">Cc</button><button type="button" class="cc-toggle" data-action="show-bcc" data-i18n="bcc">{{ __('bcc') }}</button></span></label>
        <label class="compose-line" id="ccLine" hidden><span data-i18n="cc">{{ __('cc') }}</span><input id="composeCc" type="text" autocomplete="off" data-i18n-ph="cc" placeholder="{{ __('cc') }}"></label>
        <label class="compose-line" id="bccLine" hidden><span data-i18n="bcc">{{ __('bcc') }}</span><input id="composeBcc" type="text" autocomplete="off" data-i18n-ph="bcc" placeholder="{{ __('bcc') }}"></label>
        <label class="compose-line"><span data-i18n="subject">{{ __('subject') }}</span><input id="composeSubject" type="text" data-i18n-ph="subject" placeholder="{{ __('subject') }}"></label>
        <textarea id="composeBody" data-i18n-ph="message" placeholder="{{ __('message') }}"></textarea>
        <div class="compose-editor" id="composeEditor" hidden></div>
        <div class="compose-files" id="composeFiles"></div>
        <p id="composeError" class="form-error" role="alert"></p>
        <div class="compose-actions">
          <button class="btn-go" id="sendBtn" type="submit" style="width:auto;height:44px;padding:0 18px"><span data-i18n="send">{{ __('send') }}</span> <i class="bi bi-send"></i></button>
          <label class="btn-quiet" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px"><i class="bi bi-paperclip"></i> <span data-i18n="attach">{{ __('attach') }}</span><input id="composeAttach" type="file" multiple hidden></label>
          <button class="btn-quiet" type="button" data-action="save-draft" data-i18n="saveDraft">{{ __('saveDraft') }}</button>
          <button class="btn-quiet" type="button" data-action="discard" data-i18n="discard">{{ __('discard') }}</button>
        </div>
      </form>
    </section>
    @include('partials.footer')
    <div class="backdrop" id="backdrop"></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js" integrity="sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8" crossorigin="anonymous"></script>
  <script src="/assets/js/mail.js?v={{ filemtime(public_path('assets/js/mail.js')) }}"></script>
</body>
</html>
