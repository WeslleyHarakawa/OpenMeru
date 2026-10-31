{{-- Account settings → Security: two-factor authentication with an authenticator app. --}}
<section class="settings-pane" data-pane="security" hidden>
  @if (session('two_factor_required') || (\App\Support\TwoFactor::required() && ! \App\Support\TwoFactor::enabled($user)))
    <div class="alert alert-warning" data-i18n="twoFactorRequiredNotice">{{ __('twoFactorRequiredNotice') }}</div>
  @endif

  <div id="tfaOff" @if (\App\Support\TwoFactor::enabled($user)) hidden @endif>
    <h3 class="h5" data-i18n="twoFactorHeading">{{ __('twoFactorHeading') }}</h3>
    <p class="hint" data-i18n="twoFactorIntro">{{ __('twoFactorIntro') }}</p>
    <button class="btn btn-primary" type="button" id="tfaStart" data-i18n="twoFactorEnable">{{ __('twoFactorEnable') }}</button>
  </div>

  <div id="tfaSetup" hidden>
    <h3 class="h5" data-i18n="twoFactorScanTitle">{{ __('twoFactorScanTitle') }}</h3>
    <p class="hint" data-i18n="twoFactorScanLead">{{ __('twoFactorScanLead') }}</p>
    <div class="tfa-qr" id="tfaQr"></div>
    <p class="hint"><span data-i18n="twoFactorManual">{{ __('twoFactorManual') }}</span> <code class="copy" id="tfaSecret"></code></p>
    <form id="tfaConfirmForm" class="d-flex gap-2 align-items-start flex-wrap">
      <input class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" style="max-width:180px" data-i18n-ph="twoFactorCodePh" placeholder="{{ __('twoFactorCodePh') }}" required>
      <button class="btn btn-primary" type="submit" data-i18n="twoFactorConfirm">{{ __('twoFactorConfirm') }}</button>
    </form>
  </div>

  <div id="tfaCodes" hidden>
    <h3 class="h5" data-i18n="twoFactorCodesTitle">{{ __('twoFactorCodesTitle') }}</h3>
    <p class="hint" data-i18n="twoFactorCodesLead">{{ __('twoFactorCodesLead') }}</p>
    <pre class="tfa-codes" id="tfaCodesList"></pre>
    <button class="btn btn-outline-secondary" type="button" id="tfaCodesDone" data-i18n="twoFactorCodesDone">{{ __('twoFactorCodesDone') }}</button>
  </div>

  <div id="tfaOn" @unless (\App\Support\TwoFactor::enabled($user)) hidden @endunless>
    <p><span class="pill on" data-i18n="twoFactorOn">{{ __('twoFactorOn') }}</span></p>
    <p class="hint" data-i18n="twoFactorOnLead">{{ __('twoFactorOnLead') }}</p>
    <form id="tfaDisableForm" class="d-flex gap-2 align-items-start flex-wrap mb-3">
      <input class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="40" style="max-width:220px" data-i18n-ph="twoFactorCodeOrRecovery" placeholder="{{ __('twoFactorCodeOrRecovery') }}" required>
      <button class="btn btn-outline-secondary" type="submit" data-i18n="twoFactorNewCodes" name="action" value="recovery">{{ __('twoFactorNewCodes') }}</button>
      @unless (\App\Support\TwoFactor::required())
        <button class="btn btn-outline-danger" type="submit" data-i18n="twoFactorDisable" name="action" value="disable">{{ __('twoFactorDisable') }}</button>
      @endunless
    </form>
  </div>
</section>
