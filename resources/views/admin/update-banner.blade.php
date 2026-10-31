@php
  $check = \App\Support\Updates::check();
  $status = \App\Support\Updates::status();
  $canSelf = \App\Support\Updates::canSelfUpdate();
  $command = \App\Support\Updates::installCommand();
  $show = $check['available'] || in_array($status['state'], ['running', 'done', 'failed'], true);
@endphp
@if ($show)
<div class="panel update-banner" id="updateBanner" data-status-url="{{ route('admin.update.status') }}" data-start-url="{{ route('admin.update.start') }}" data-state="{{ $status['state'] }}">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="flex-grow-1">
      @if ($status['state'] === 'running')
        <h2 class="mb-1"><i class="bi bi-arrow-repeat"></i> {{ __('admin.updating') }}</h2>
        <p class="hint mb-0">{{ __('admin.updatingHint') }}</p>
      @elseif ($status['state'] === 'done')
        <h2 class="mb-1"><i class="bi bi-check-circle"></i> {{ __('admin.updated', ['version' => $status['to'] ?? \App\Support\Version::current()]) }}</h2>
      @elseif ($status['state'] === 'failed')
        <h2 class="mb-1"><i class="bi bi-x-circle"></i> {{ __('admin.updateFailed') }}</h2>
        <p class="hint mb-0">{{ __('admin.updateFailedHint') }}</p>
      @else
        <h2 class="mb-1"><i class="bi bi-cloud-arrow-down"></i> {{ __('admin.updateAvailable', ['version' => $check['latest']]) }}</h2>
        <p class="hint mb-0">{{ __('admin.updateCurrent', ['version' => $check['current']]) }}
          @if ($check['url'])<a href="{{ $check['url'] }}" target="_blank" rel="noopener">{{ __('admin.updateWhatsNew') }}</a>@endif
        </p>
      @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
      @if ($status['state'] === 'idle' && $check['available'])
        @if ($canSelf)
          <button type="button" class="btn btn-primary" data-update-start><i class="bi bi-download"></i> {{ __('admin.updateNow') }}</button>
        @endif
      @endif
      @if (in_array($status['state'], ['done', 'failed'], true))
        <form method="POST" action="{{ route('admin.update.dismiss') }}">@csrf<button class="btn btn-outline-secondary" type="submit">{{ __('admin.close') }}</button></form>
      @endif
    </div>
  </div>
  @if ($status['state'] === 'idle' && $check['available'])
    <p class="hint mt-3 mb-1">{{ $canSelf ? __('admin.updateOrTerminal') : __('admin.updateTerminalOnly') }}</p>
    <code class="copy">{{ $command }}</code>
  @endif
  <pre id="updateLog" class="mt-3 mb-0 small" style="white-space:pre-wrap;background:var(--bg);border-radius:10px;padding:12px;max-height:260px;overflow:auto;{{ $status['log'] ? '' : 'display:none' }}">{{ $status['log'] }}</pre>
</div>
<script>
  (function () {
    var banner = document.getElementById("updateBanner");
    if (!banner) return;
    var log = document.getElementById("updateLog");
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    function poll() {
      fetch(banner.dataset.statusUrl, { headers: { Accept: "application/json" }, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (s) {
          if (s.log) { log.style.display = ""; log.textContent = s.log; log.scrollTop = log.scrollHeight; }
          if (s.state === "running") setTimeout(poll, 3000);
          else if (s.state === "done" || s.state === "failed") location.reload();
        })
        .catch(function () { setTimeout(poll, 5000); });
    }
    var start = banner.querySelector("[data-update-start]");
    if (start) start.addEventListener("click", function () {
      start.disabled = true;
      fetch(banner.dataset.startUrl, { method: "POST", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf }, credentials: "same-origin" })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) { if (res.ok) { banner.dataset.state = "running"; poll(); } else { start.disabled = false; alert(res.data.detail || res.data.message || "error"); } });
    });
    if (banner.dataset.state === "running") poll();
  })();
</script>
@endif
