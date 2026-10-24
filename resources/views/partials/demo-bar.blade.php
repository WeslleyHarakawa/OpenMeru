@if (auth()->user()?->is_demo)
  <div class="demo-bar">
    <span><i class="bi bi-eye"></i> {{ __('demoBar') }}</span>
    <span class="demo-bar-links">
      <a href="{{ auth()->user()->is_admin ? '/demo' : '/demo/admin' }}">{{ auth()->user()->is_admin ? __('demoTryMailbox') : __('demoTryAdmin') }}</a>
      <a href="/#install">{{ __('demoGetIt') }}</a>
      <form method="POST" action="/logout">@csrf<input type="hidden" name="next" value="/"><button type="submit">{{ __('demoExit') }}</button></form>
    </span>
  </div>

  {{-- Shown whenever a demo visitor tries to send or change something. --}}
  <div class="demo-modal" id="demoModal" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle" hidden>
    <div class="demo-modal-card">
      <div class="demo-modal-icon"><i class="bi bi-info-circle"></i></div>
      <h2 id="demoModalTitle">{{ __('demoTitle') }}</h2>
      <p id="demoModalText">{{ __('demoBlocked') }}</p>
      <div class="demo-modal-actions">
        <a class="demo-modal-link" href="/#install">{{ __('demoGetIt') }}</a>
        <button type="button" class="demo-modal-ok" data-demo-close>{{ __('demoOk') }}</button>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var modal = document.getElementById("demoModal");
      var text = document.getElementById("demoModalText");
      var fallback = text.textContent;
      window.demoNotice = function (message) {
        text.textContent = message || fallback;
        modal.hidden = false;
        modal.querySelector("[data-demo-close]").focus();
      };
      function close() { modal.hidden = true; }
      modal.addEventListener("click", function (e) { if (e.target === modal || e.target.closest("[data-demo-close]")) close(); });
      document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !modal.hidden) close(); });
      @if (session('demo_notice'))
        window.demoNotice(@json(session('demo_notice')));
      @endif
    })();
  </script>
@endif
