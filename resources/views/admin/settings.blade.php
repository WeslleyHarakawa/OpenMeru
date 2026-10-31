@extends('admin.layout')

@section('title', __('admin.smtp'))

@section('content')
<div class="settings-layout">
  <nav class="settings-nav" aria-label="{{ __('admin.smtp') }}">
    <a href="#general" data-pane-link="general"><i class="bi bi-sliders"></i> {{ __('admin.general') }}</a>
    <a href="#smtp" data-pane-link="smtp"><i class="bi bi-send"></i> {{ __('admin.menuSmtp') }}</a>
    <a href="#push" data-pane-link="push"><i class="bi bi-bell"></i> {{ __('admin.menuPush') }}</a>
    <a href="#translate" data-pane-link="translate"><i class="bi bi-translate"></i> {{ __('admin.menuTranslate') }}</a>
    <a href="#inbound" data-pane-link="inbound"><i class="bi bi-cloud-arrow-down"></i> {{ __('admin.menuInbound') }}</a>
    <a href="#security" data-pane-link="security"><i class="bi bi-shield-lock"></i> {{ __('admin.menuSecurity') }}</a>
    <a href="#updates" data-pane-link="updates"><i class="bi bi-arrow-repeat"></i> {{ __('admin.menuUpdates') }}</a>
  </nav>

  <div class="settings-panes">

  <section class="panel" data-pane="general">
    <h2><i class="bi bi-sliders"></i> {{ __('admin.generalTitle') }}</h2>
    <p class="hint">{{ __('admin.generalLead') }}</p>
    <form method="POST" action="{{ route('admin.settings.brand') }}" class="row g-3" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="col-md-6">
        <label class="form-label" for="brand_name">{{ __('admin.brandName') }}</label>
        <input class="form-control" id="brand_name" name="brand_name" maxlength="60" value="{{ old('brand_name', $brand_name) }}" placeholder="{{ __('admin.brandNamePh') }}">
      </div>
      @foreach (['logo' => ['admin.brandLogo', 'admin.brandLogoHint', 'image/png,image/jpeg,image/webp,image/svg+xml'], 'icon' => ['admin.brandIcon', 'admin.brandIconHint', 'image/png,image/jpeg,image/webp,image/svg+xml'], 'favicon' => ['admin.brandFavicon', 'admin.brandFaviconHint', 'image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml'], 'background' => ['admin.loginBackground', 'admin.loginBackgroundHint', 'image/png,image/jpeg,image/webp']] as $asset => [$label, $hint, $accept])
      <div class="col-md-6">
        <label class="form-label" for="{{ $asset }}">{{ __($label) }}</label>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          @if ($brand_assets[$asset])
            <img class="asset-thumb {{ $asset }}" src="{{ $brand_assets[$asset] }}" alt="">
          @elseif ($asset !== 'background')
            @include('partials.logo', ['class' => 'sm'])
          @endif
          <label class="file-pick" for="{{ $asset }}">
            <input id="{{ $asset }}" name="{{ $asset }}" type="file" accept="{{ $accept }}" hidden>
            <span class="btn btn-outline-secondary"><i class="bi bi-upload"></i> {{ __('admin.chooseFile') }}</span>
            <span class="file-name hint" data-empty="{{ __('admin.noFileChosen') }}">{{ __('admin.noFileChosen') }}</span>
          </label>
          @if ($brand_assets[$asset])
            <button class="btn btn-outline-secondary btn-sm" type="submit" name="remove_{{ $asset }}" value="1"><i class="bi bi-x-circle"></i> {{ __('admin.remove') }}</button>
          @endif
        </div>
        <div class="hint mt-1">{{ __($hint) }}</div>
      </div>
      @endforeach
      <div class="col-md-6">
        <label class="form-label">{{ __('admin.brandColors') }}</label>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <label class="color-pick"><input type="color" name="brand_primary" value="{{ old('brand_primary', $brand_primary) }}"> <span>{{ __('admin.primaryColor') }}</span></label>
          <label class="color-pick"><input type="color" name="brand_secondary" value="{{ old('brand_secondary', $brand_secondary) }}"> <span>{{ __('admin.secondaryColor') }}</span></label>
        </div>
        <div class="hint mt-1">{{ __('admin.colorsHint') }}</div>
      </div>
      <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ __('admin.brandSave') }}</button>
        @if ($brand_colors_custom)
          <button class="btn btn-outline-secondary" type="submit" name="reset_colors" value="1"><i class="bi bi-arrow-counterclockwise"></i> {{ __('admin.resetColors') }}</button>
        @endif
      </div>
    </form>
  </section>

  <section class="panel" data-pane="smtp">
    <h2><i class="bi bi-send"></i> {{ __('admin.smtpTitle') }}</h2>
    <p class="hint">{{ __('admin.smtpLead') }}</p>
    <form method="POST" action="{{ route('admin.settings.update') }}" class="row g-3" autocomplete="off" id="smtpForm">
      @csrf @method('PUT')
      <div class="col-md-6">
        <label class="form-label" for="smtp_provider">{{ __('admin.provider') }}</label>
        <select class="form-select" id="smtp_provider" name="smtp_provider">
          @foreach (\App\Mail\Outbox::PROVIDERS as $key => $preset)
            <option value="{{ $key }}" data-host="{{ $preset['host'] }}" data-port="{{ $preset['port'] }}" data-login="{{ $preset['login'] ?? '' }}" data-api="{{ $preset['api'] ? 1 : 0 }}" @selected(old('smtp_provider', $smtp_provider) === $key)>{{ $key === 'custom' ? __('admin.providerCustom') : $preset['name'] }}</option>
          @endforeach
        </select>
        <div class="hint mt-1" id="providerHint">{{ __('admin.providerHint') }}</div>
      </div>
      <div class="col-md-6"></div>
      <div class="col-md-6">
        <label class="form-label" for="smtp_host">{{ __('admin.smtpServer') }}</label>
        <input class="form-control" id="smtp_host" name="smtp_host" value="{{ old('smtp_host', $smtp_host) }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="smtp_port">{{ __('admin.port') }}</label>
        <input class="form-control" id="smtp_port" name="smtp_port" type="number" value="{{ old('smtp_port', $smtp_port) }}" required>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="smtp_username">{{ __('admin.smtpLogin') }}</label>
        <input class="form-control" id="smtp_username" name="smtp_username" value="{{ old('smtp_username', $smtp_username) }}" required>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="smtp_password">{{ __('admin.smtpKey') }} @if ($smtp_password) <span class="pill on">{{ __('admin.pillSaved') }}</span> @else <span class="pill off">{{ __('admin.pillNotSet') }}</span> @endif</label>
        <div class="input-group">
          <input class="form-control" id="smtp_password" name="smtp_password" type="password" value="{{ $smtp_password }}" placeholder="xsmtpsib-..." autocomplete="new-password">
          <button class="btn btn-outline-secondary" type="button" data-reveal="smtp_password" title="{{ __('admin.showHide') }}"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <div class="col-md-6" id="brevoApiField">
        <label class="form-label" for="brevo_api_key">{{ __('admin.brevoKey') }} @if ($brevo_api_key) <span class="pill on">{{ __('admin.pillSaved') }}</span> @else <span class="pill off">{{ __('admin.pillNotSet') }}</span> @endif</label>
        <div class="input-group">
          <input class="form-control" id="brevo_api_key" name="brevo_api_key" type="password" value="{{ $brevo_api_key }}" placeholder="xkeysib-..." autocomplete="new-password">
          <button class="btn btn-outline-secondary" type="button" data-reveal="brevo_api_key" title="{{ __('admin.showHide') }}"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="system_from">{{ __('admin.systemFrom') }}</label>
        <input class="form-control" id="system_from" name="system_from" type="email" value="{{ old('system_from', $system_from) }}" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ __('admin.saveSettings') }}</button>
      </div>
    </form>
    <hr>
    <h2><i class="bi bi-envelope-check"></i> {{ __('admin.testTitle') }}</h2>
    <form method="POST" action="{{ route('admin.settings.test') }}" class="row g-3">
      @csrf
      <div class="col-md-8">
        <input class="form-control" name="test_to" type="email" required placeholder="{{ __('admin.testPh') }}">
      </div>
      <div class="col-md-4">
        <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-send"></i> {{ __('admin.sendTest') }}</button>
      </div>
    </form>
  </section>

  <section class="panel" data-pane="push">
    <h2><i class="bi bi-bell"></i> {{ __('admin.pushTitle') }}</h2>
    <p class="hint">{{ __('admin.pushLead', ['count' => $push_devices]) }}</p>
    @if ($vapid_public)
      <label class="form-label">{{ __('admin.publicKey') }}</label>
      <div class="input-group mb-3">
        <input class="form-control" id="vapid_public" type="password" value="{{ $vapid_public }}" readonly>
        <button class="btn btn-outline-secondary" type="button" data-reveal="vapid_public" title="{{ __('admin.showHide') }}"><i class="bi bi-eye"></i></button>
      </div>
      <label class="form-label">{{ __('admin.privateKey') }}</label>
      <div class="input-group mb-3">
        <input class="form-control" id="vapid_private" type="password" value="{{ $vapid_private }}" readonly>
        <button class="btn btn-outline-secondary" type="button" data-reveal="vapid_private" title="{{ __('admin.showHide') }}"><i class="bi bi-eye"></i></button>
      </div>
    @else
      <p><span class="pill off">{{ __('admin.notGenerated') }}</span></p>
    @endif
    <form method="POST" action="{{ route('admin.settings.vapid') }}" @if ($vapid_public) onsubmit="return confirm(@js(__('admin.genConfirm')));" @endif>
      @csrf
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-key"></i> {{ $vapid_public ? __('admin.genNew') : __('admin.gen') }}</button>
    </form>
  </section>

  <section class="panel" data-pane="translate">
    <h2><i class="bi bi-translate"></i> {{ __('admin.trTitle') }}</h2>
    <p class="hint">{{ __('admin.trHint') }}</p>
    <form method="POST" action="{{ route('admin.settings.translate') }}" class="row g-3" autocomplete="off">
      @csrf
      @method('PUT')
      <div class="col-md-6">
        <label class="form-label" for="cf_account_id">{{ __('admin.trAccount') }}</label>
        <input class="form-control" id="cf_account_id" name="cf_account_id" value="{{ old('cf_account_id', $cf_account_id) }}" placeholder="0123456789abcdef0123456789abcdef">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="cf_ai_token">{{ __('admin.trToken') }}</label>
        <input class="form-control" id="cf_ai_token" name="cf_ai_token" type="password" autocomplete="new-password" placeholder="{{ $cf_ai_token_set ? __('admin.trTokenKeep') : '' }}">
      </div>
      <div class="col-12 d-flex align-items-center gap-3">
        <button class="btn btn-primary" type="submit">{{ __('admin.trSave') }}</button>
        <span class="pill {{ $cf_account_id && $cf_ai_token_set ? 'on' : 'off' }}">{{ $cf_account_id && $cf_ai_token_set ? __('admin.trOn') : __('admin.trOff') }}</span>
      </div>
    </form>
  </section>

  <section class="panel" data-pane="inbound">
    <h2><i class="bi bi-cloud-arrow-down"></i> {{ __('admin.inboundTitle') }}</h2>
    <p class="hint">{{ __('admin.inboundLead', ['header' => 'X-OpenMeru-Secret']) }}</p>
    <p class="mb-1">URL: <code class="copy">{{ $inbound_url }}?to=&lt;{{ __('admin.recipient') }}&gt;</code></p>
    <p class="mb-0">{{ __('admin.secret') }}: @if ($inbound_secret_set) <span class="pill on">{{ __('admin.secretSet') }}</span> @else <span class="pill off">{{ __('admin.secretMissing') }}</span> @endif</p>
  </section>

  <section class="panel" data-pane="security">
    <h2><i class="bi bi-shield-lock"></i> {{ __('admin.securityTitle') }}</h2>
    <form method="POST" action="{{ route('admin.settings.security') }}" class="d-flex flex-column gap-3">
      @csrf @method('PUT')
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="allow_signup" name="allow_signup" value="1" @checked($allow_signup)>
        <label class="form-check-label" for="allow_signup"><strong>{{ __('admin.allowSignup') }}</strong><div class="hint">{{ __('admin.allowSignupHint', ['url' => url('/signup')]) }}</div></label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="require_2fa" name="require_2fa" value="1" @checked($require_2fa)>
        <label class="form-check-label" for="require_2fa"><strong>{{ __('admin.require2fa') }}</strong><div class="hint">{{ __('admin.require2faHint') }}</div></label>
      </div>
      <div><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ __('admin.saveSettings') }}</button></div>
    </form>
  </section>

  <section class="panel" data-pane="updates">
    <h2><i class="bi bi-arrow-repeat"></i> {{ __('admin.menuUpdates') }} <span class="pill admin">v{{ \App\Support\Version::current() }}</span></h2>
    <form method="POST" action="{{ route('admin.update.check') }}" class="d-flex align-items-center gap-3 flex-wrap">
      @csrf
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-cloud-arrow-down"></i> {{ __('admin.checkUpdates') }}</button>
      <span class="hint">{{ __('admin.updateOrTerminal') }} <code class="copy">{{ \App\Support\Updates::installCommand() }}</code></span>
    </form>
  </section>

  </div>
</div>
<script>
  (function () {
    var provider = document.getElementById("smtp_provider");
    if (provider) {
      var apply = function (fill) {
        var o = provider.options[provider.selectedIndex];
        var host = document.getElementById("smtp_host"), port = document.getElementById("smtp_port"), login = document.getElementById("smtp_username");
        if (fill && o.dataset.host) { host.value = o.dataset.host; port.value = o.dataset.port; if (o.dataset.login) login.value = o.dataset.login; }
        host.readOnly = !!o.dataset.host && o.value !== "custom" && o.value !== "ses";
        document.getElementById("brevoApiField").hidden = o.dataset.api !== "1";
      };
      provider.addEventListener("change", function () { apply(true); });
      apply(false);
    }
    document.querySelectorAll(".file-pick input[type=file]").forEach(function (input) {
      input.addEventListener("change", function () {
        var name = input.parentElement.querySelector(".file-name");
        name.textContent = input.files && input.files[0] ? input.files[0].name : name.dataset.empty;
      });
    });
    var links = document.querySelectorAll("[data-pane-link]");
    var panes = document.querySelectorAll("[data-pane]");
    function show(name) {
      var found = false;
      panes.forEach(function (p) { var on = p.dataset.pane === name; p.hidden = !on; if (on) found = true; });
      if (!found) return show("general");
      links.forEach(function (l) { l.classList.toggle("active", l.dataset.paneLink === name); });
    }
    links.forEach(function (l) { l.addEventListener("click", function (e) { e.preventDefault(); history.replaceState(null, "", "#" + l.dataset.paneLink); show(l.dataset.paneLink); }); });
    show((location.hash || "#general").slice(1));
  })();
</script>
@endsection
